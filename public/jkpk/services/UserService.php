<?php
/**
 * 用户服务类
 * 封装用户相关的业务逻辑，包括注册、登录、用户信息管理等
 */

require_once __DIR__ . '/../models/MySQLModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/RedisModel.php';
require_once __DIR__ . '/QueueService.php';
require_once __DIR__ . '/UserLogService.php';

class UserService {
    private $mysqlModel;
    private $userModel;
    private $redisModel;
    private $queueService;
    private $userLogService;
    private $config = [];
    public function __construct() {
        $this->config = require_once __DIR__ . '/../config/config.php';
        $this->userLogService = UserLogService::getInstance();
        $this->userModel = new UserModel();
        $this->redisModel = RedisModel::getInstance();
        $this->queueService = QueueService::getInstance();
    }

    public function generateSalt($length = 8) {
        // 使用 cryptographically secure 随机数生成器
        return bin2hex(random_bytes($length / 2)); // 因为1字节=2个十六进制字符
    }
    
    /**
     * 用户注册
     */
    public function register($userData) {
        try {
            // 参数验证
            if (empty($userData['username']) || empty($userData['password'])) {
                return [
                    'success' => false,
                    'message' => '账号和密码不能为空',
                    'code' => 400
                ];
            }
            
            $username = trim($userData['username']);

            if (mb_strlen($username, 'UTF-8') < 4) {
                return [
                    'success' => false,
                    'message' => '用户名必须至少4个字符',
                    'code' => 400
                ];
            }

            // 检查账号是否已存在（在Redis中）
            if ($this->redisModel->get("user:username:{$username}")) {
                return [
                    'success' => false,
                    'message' => '账号已存在',
                    'code' => 409
                ];
            }
            
            // 生成用户ID
            $userId = $this->redisModel->incr('user:next_id');
            
            // 确定账号类型和用户名
            $mobile = null;
            $email = null;
            $password = $userData['password'];
            if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
                $email = $username;
            } elseif (preg_match('/^1[3-9]\d{9}$/', $username)) {
                $mobile = $username;
                $password = !empty($userData['password']) ? $userData['password'] : $mobile;
            }
            $salt  = $this->generateSalt();
            // 准备用户数据
            $currentTime = time();
            // 默认昵称
            $nickname = '用户' . substr($username, -4);
            $newUserData = [
                'id' => $userId,
                'group_id' => 0,
                'username' => $username,
                'nickname' => $nickname,
                'password' => md5($password . $salt),
                'salt' => $salt,
                'email' => '',
                'mobile' => '',
                'avatar' => '',
                'level' => 0,
                'gender' => 0,
                'birthday' => null,
                'bio' => '',
                'money' => 0.00,
                'score' => 0,
                'successions' => 1,
                'maxsuccessions' => 1,
                'prevtime' => null,
                'logintime' => $currentTime,
                'loginip' => $this->getClientIP(),
                'loginfailure' => 0,
                'loginfailuretime' => null,
                'joinip' => $this->getClientIP(),
                'jointime' => $currentTime,
                'createtime' => $currentTime,
                'updatetime' => $currentTime,
                'token' => '',
                'status' => 'normal',
                'verification' => ''
            ];

            // 存储到Redis
            $userKey = "user:{$userId}";
            $accountKey = "user:username:{$username}";
            
            // 存储用户信息
            $this->redisModel->hMSet($userKey, $newUserData);
            // 设置账号到用户ID的映射
            $this->redisModel->set($accountKey, $userId);
            
            // 生成登录令牌
            $token = $this->generateToken($userId);
            
            // 缓存用户会话
            $sessionData = [
                'user_id' => $userId,
                'username' => $username,
                'login_time' => time(),
                'login_ip' => $this->getClientIP()
            ];
            $this->redisModel->setUserSession($userId, $sessionData);
                
            // 获取完整用户信息
//            $userInfo = $this->userModel->getUserById($userId);
//            unset($userInfo['password']); // 移除密码哈希

            // 缓存用户信息
            $this->redisModel->setUserInfo($userId, $newUserData);
            
            // 发送用户注册数据到队列进行异步处理
            $this->publishUserRegistrationToQueue($newUserData);
            // 发送用户操作日志数据到队列进行异步处理
            $userData['password'] = $userData['confirm_password'] = '***';
            $this->userLogService->publishUserLogToQueue([
                'title' => '注册',
                'user_id' => $userId,
                'username' => $username,
                'content' => json_encode($userData, JSON_UNESCAPED_UNICODE),
            ]);
            
            unset($newUserData['password']); // 移除密码哈希
            unset($newUserData['salt']);

            return [
                'success' => true,
                'message' => '注册成功',
                'data' => [
                    'user_id' => $userId,
                    'token' => $token,
                    'user_info' => $newUserData
                ],
                'code' => 201
            ];
        } catch (Exception $e) {
            error_log("[UserService] 注册失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '注册失败，请稍后重试',
                'code' => 500
            ];
        }
    }
    
    /**
     * 用户登录
     */
    public function login($identifier, $password) {
        try {
            // 参数验证
            if (empty($identifier) || empty($password)) {
                return [
                    'success' => false,
                    'message' => '账号和密码不能为空',
                    'code' => 400
                ];
            }
            
            // 检查登录失败次数限制
            if ($this->redisModel->isLoginBlocked($identifier)) {
                return [
                    'success' => false,
                    'message' => '登录失败次数过多，请稍后再试',
                    'code' => 429
                ];
            }

            // 从Redis获取用户信息
            $userId = $this->redisModel->get("user:username:{$identifier}");
            
            if (!$userId) {
                // 记录登录失败
                $this->redisModel->recordLoginFailure($identifier);
                
                return [
                    'success' => false,
                    'message' => '账号错误',
                    'code' => 401
                ];
            }
            
            // 获取用户详细信息
            $user = $this->redisModel->hGetAll("user:{$userId}");
            
            if (!$user || empty($user)) {
                // 记录登录失败
                $this->redisModel->recordLoginFailure($identifier);
                
                return [
                    'success' => false,
                    'message' => '账号获取失败',
                    'code' => 401
                ];
            }
            $password = md5($password . $user['salt']);
            // 验证密码
            if ($password != $user['password']) {
                // 记录登录失败
                $this->redisModel->recordLoginFailure($identifier);
                
                return [
                    'success' => false,
                    'message' => '账号或密码错误',
                    'code' => 401
                ];
            }
            
            // 检查用户状态
            if ($user['status'] == 'deny') {
                return [
                    'success' => false,
                    'message' => '账户已被禁用，请联系客服',
                    'code' => 403
                ];
            }
            
            // 登录成功，清除失败记录
            $this->redisModel->clearLoginFailure($identifier);
            
            // 更新最后登录时间
            $currentTime = time();
            $this->redisModel->hMSet("user:{$userId}", [
                'prevtime' => $user['logintime'] ?? null,
                'logintime' => $currentTime,
                'loginip' => $this->getClientIP(),
                'updatetime' => $currentTime
            ]);
            
            // 生成登录令牌
            $token = $this->generateToken($userId);
            $user['user_id'] = intval($userId);
            // 缓存用户会话
            $sessionData = [
                'user_id' => $userId,
                'username' => $user['username'],
                'login_time' => time(),
                'login_ip' => $this->getClientIP()
            ];
            $this->redisModel->setUserSession($userId, $sessionData);

            // 移除敏感信息
            unset($user['password']);
            unset($user['salt']);
            // 头像
            $user['avatar'] = $this->formatAvatarUrl($user['avatar'] ?? '');
            // 发送用户操作日志数据到队列进行异步处理
            $this->userLogService->publishUserLogToQueue([
                'title' => '登录',
                'user_id' => $userId,
                'username' => $user['username'],
                'content' => json_encode(
                    array('username' => $identifier, 'password' => '***'),
                    JSON_UNESCAPED_UNICODE
                ),
            ]);
            return [
                'success' => true,
                'message' => '登录成功',
                'data' => [
                    'user_id' => $userId,
                    'token' => $token,
                    'user_info' => $user
                ],
                'code' => 200
            ];
            
        } catch (Exception $e) {
            error_log("[UserService] 登录失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '登录失败，请稍后重试',
                'code' => 500
            ];
        }
    }

    
    /**
     * 验证用户名格式
     */
    private function validateUsername($username) {
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
    }
    
    /**
     * 验证密码强度
     */
    private function validatePassword($password) {
        // 至少6位，包含字母和数字
        return strlen($password) >= 6 && preg_match('/[a-zA-Z]/', $password) && preg_match('/[0-9]/', $password);
    }
    
    /**
     * 验证验证码
     */
    private function verifyCode($identifier, $code, $type = 'register') {
        $storedCode = $this->redisModel->getVerificationCode($identifier, $type);
        return $storedCode && $storedCode === $code;
    }
    
    /**
     * 生成登录令牌
     */
    private function generateToken($userId) {
        $payload = [
            'user_id' => $userId,
            'timestamp' => time(),
            'random' => bin2hex(random_bytes(16))
        ];
        
        return base64_encode(json_encode($payload));
    }
    
    /**
     * 获取客户端IP
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * 获取用户代理
     */
    private function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
    
    /**
     * 发送验证码（预留接口）
     */
    public function sendVerificationCode($identifier, $type = 'register') {
        try {
            // 生成6位数字验证码
            $code = sprintf('%06d', mt_rand(0, 999999));
            
            // 存储到Redis
            $this->redisModel->setVerificationCode($identifier, $code, $type);
            
            // 这里应该调用短信或邮件服务发送验证码
            // 目前只是记录日志
            error_log("[UserService] 验证码发送: {$identifier} -> {$code} (类型: {$type})");
            
            return [
                'success' => true,
                'message' => '验证码发送成功',
                'code' => 200
            ];
            
        } catch (Exception $e) {
            error_log("[UserService] 验证码发送失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '验证码发送失败',
                'code' => 500
            ];
        }
    }
    
    /**
     * 获取用户信息
     * 优先从Redis缓存获取，如果没有则从MySQL获取并更新缓存
     */
    public function getUserInfo($userId) {
        try {
            // 先从Redis缓存获取
            $userInfo = $this->redisModel->getUserInfo($userId);
            
            if ($userInfo) {
                // 移除敏感信息
                unset($userInfo['password']);
                
                // 格式化头像URL
                if (isset($userInfo['avatar'])) {
                    $userInfo['avatar'] = $this->formatAvatarUrl($userInfo['avatar']);
                }
                
                return [
                    'success' => true,
                    'data' => $userInfo,
                    'code' => 200
                ];
            }
            
            // 如果缓存中没有，从MySQL获取（这里暂时返回空，因为当前只使用Redis）
            return [
                'success' => false,
                'message' => '用户不存在',
                'code' => 404
            ];
            
        } catch (Exception $e) {
            error_log("[UserService] 获取用户信息失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '获取用户信息失败',
                'code' => 500
            ];
        }
    }
    
    /**
     * 验证用户令牌
     */
    public function validateUserToken($userId, $token) {
        try {
            // 获取用户会话信息
            $sessionData = $this->redisModel->getUserSession($userId);
            
            if (!$sessionData) {
                return false;
            }
            
            // 解析令牌
            $tokenData = json_decode(base64_decode($token), true);
            
            if (!$tokenData || !isset($tokenData['user_id']) || $tokenData['user_id'] != $userId) {
                return false;
            }
            
            // 检查令牌是否过期（24小时）
//            $tokenTime = $tokenData['timestamp'] ?? 0;
//            $currentTime = time();
//
//            if ($currentTime - $tokenTime > 86400 * 7) { // 7天
//                return false;
//            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("[UserService] 令牌验证失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 修改用户昵称
     */
    public function updateNickname($userId, $nickname) {
        try {
            // 参数验证
            if (empty($nickname)) {
                return [
                    'success' => false,
                    'message' => '昵称不能为空',
                    'code' => 400
                ];
            }

            // 昵称长度验证
            $nicknameLength = mb_strlen($nickname, 'UTF-8');
            if ($nicknameLength > 20 || $nicknameLength < 2) {
                return [
                    'success' => false,
                    'message' => '昵称长度2-20个字符之间',
                    'code' => 400
                ];
            }
            
            // 昵称格式验证（不能包含特殊字符）
            if (preg_match('/[<>"\'\/\\]/', $nickname)) {
                return [
                    'success' => false,
                    'message' => '昵称不能包含特殊字符',
                    'code' => 400
                ];
            }
            
            // 更新Redis中的用户信息
            $updateData = [
                'nickname' => $nickname,
                'updatetime' => time(),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->redisModel->hMSet("user:{$userId}", $updateData);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => '修改昵称失败',
                    'code' => 500
                ];
            }
            
            // 清除用户信息缓存，强制重新获取
            $this->redisModel->delete("user_info:{$userId}");
            
            // 获取更新后的用户信息
            $userInfo = $this->redisModel->hGetAll("user:{$userId}");
            unset($userInfo['password']);
            unset($userInfo['salt']);
            // 发送用户数据到队列进行异步处理
            $this->publishUserUpdateToQueue($userId, $updateData);
            // 发送用户操作日志数据到队列进行异步处理
            $this->userLogService->publishUserLogToQueue([
                'title' => '修改昵称',
                'user_id' => $userId,
                'username' => $userInfo['username'],
                'content' => json_encode($updateData, JSON_UNESCAPED_UNICODE),
            ]);
            return [
                'success' => true,
                'message' => '昵称修改成功',
                'data' => $userInfo,
                'code' => 200
            ];
            
        } catch (Exception $e) {
            error_log("[UserService] 修改昵称失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '修改昵称失败，请稍后重试',
                'code' => 500
            ];
        }
    }
    
    /**
     * 修改用户头像
     * 支持两种方式：
     * 1. 上传图片文件（通过$_FILES获取）
     * 2. 选择默认头像（传入avatar_1、avatar_2等名称）
     */
    public function updateAvatar($userId, $avatarData = null) {
        try {
            $avatarUrl = '';

            // 检查是否有文件上传
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                // 处理文件上传
                $result = $this->handleAvatarUpload($_FILES['avatar'], $userId);
                if (!$result['success']) {
                    return $result;
                }
                $avatarUrl = $result['avatar'];
                
            } elseif (!empty($avatarData)) {
                // 处理默认头像或URL
                if (preg_match('/^avatar_\d+$/', $avatarData)) {
                    // 默认头像，直接存储名称
                    $avatarUrl = $avatarData;
                } elseif (filter_var($avatarData, FILTER_VALIDATE_URL)) {
                    // 外部URL
                    if (strlen($avatarData) > 500) {
                        return [
                            'success' => false,
                            'message' => '头像URL长度不能超过500个字符',
                            'code' => 400
                        ];
                    }
                    $avatarUrl = $avatarData;
                } else {
                    return [
                        'success' => false,
                        'message' => '头像格式不正确',
                        'code' => 400
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => '请选择头像或上传图片',
                    'code' => 400
                ];
            }
            
            // 更新Redis中的用户信息
            $updateData = [
                'avatar' => $avatarUrl,
                'updatetime' => time(),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->redisModel->hMSet("user:{$userId}", $updateData);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => '修改头像失败',
                    'code' => 500
                ];
            }
            
            // 清除用户信息缓存
            $this->redisModel->delete("user_info:{$userId}");
            
            // 获取更新后的用户信息
            $userInfo = $this->redisModel->hGetAll("user:{$userId}");
            unset($userInfo['password']);
            unset($userInfo['salt']);

            // 处理头像URL输出格式
            $userInfo['avatar'] = $this->formatAvatarUrl($userInfo['avatar']);
            // 发送用户注册数据到队列进行异步处理
            $this->publishUserUpdateToQueue($userId, $updateData);
            // 发送用户操作日志数据到队列进行异步处理
            $this->userLogService->publishUserLogToQueue([
                'title' => '修改头像',
                'user_id' => $userId,
                'username' => $userInfo['username'],
                'content' => json_encode($updateData, JSON_UNESCAPED_UNICODE),
            ]);
            return [
                'success' => true,
                'message' => '头像修改成功',
                'data' => $userInfo,
                'code' => 200
            ];
            
        } catch (Exception $e) {
            error_log("[UserService] 修改头像失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '修改头像失败，请稍后重试',
                'code' => 500
            ];
        }
    }
    
    /**
     * 处理头像文件上传
     */
    private function handleAvatarUpload($file, $userId) {
        try {
            // 检查文件大小（1MB = 1048576 bytes）
            if ($file['size'] > 1048576) {
                return [
                    'success' => false,
                    'message' => '头像文件大小不能超过1MB',
                    'code' => 400
                ];
            }

            // 检查文件类型
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowedTypes)) {
                return [
                    'success' => false,
                    'message' => '只支持JPG、PNG、GIF格式的图片',
                    'code' => 400
                ];
            }
            
            // 生成文件名
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'avatar_' . $userId . '_' . time() . '.' . strtolower($extension);

            // 确保目录存在
            $uploadDir = __DIR__ . '/../data/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0757, true);
            }

            $filePath = $uploadDir . $fileName;
            // 移动上传的文件
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return [
                    'success' => false,
                    'message' => '文件上传失败',
                    'code' => 500
                ];
            }
            
            // 删除用户之前的头像文件（如果存在）
            $this->deleteOldAvatar($userId);
            
            return [
                'success' => true,
                'avatar' => $fileName
            ];
            
        } catch (Exception $e) {
            error_log("[UserService] 头像上传失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '头像上传失败',
                'code' => 500
            ];
        }
    }
    
    /**
     * 删除用户旧头像文件
     */
    private function deleteOldAvatar($userId) {
        try {
            $userInfo = $this->redisModel->hGetAll("user:{$userId}");
            if (!empty($userInfo['avatar'])) {
                $oldAvatar = $userInfo['avatar'];
                
                // 只删除上传的文件，不删除默认头像
                if (!preg_match('/^avatar_\d+$/', $oldAvatar) && !filter_var($oldAvatar, FILTER_VALIDATE_URL)) {
                    $oldFilePath = __DIR__ . '/../data/avatars/' . $oldAvatar;
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("[UserService] 删除旧头像失败: " . $e->getMessage());
        }
    }
    
    /**
     * 格式化头像URL输出
     * 默认头像返回名称，上传的文件返回完整HTTP地址
     */
    private function formatAvatarUrl($avatarUrl) {
        if (empty($avatarUrl)) {
            return '';
        }
        
        // 默认头像，直接返回名称
        if (preg_match('/^avatar_\d+$/', $avatarUrl)) {
            return $avatarUrl;
        }
        
        // 外部URL，直接返回
        if (filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
            return $avatarUrl;
        }
        
        // 上传的文件，返回完整HTTP地址
//        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
//        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
//        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        
        return $this->config['HTTP_HOST'] . '/data/avatars/' . $avatarUrl;
    }

    /**
     * 带短信验证的手机号修改
     */
    public function updatePhoneWithSms($userId, $mobile, $smsCode, $event = 'bind') {
        try {
            // 参数验证
            if (empty($smsCode)) {
                return [
                    'success' => false,
                    'message' => '短信验证码不能为空',
                    'code' => 400
                ];
            }
            
            // 验证短信验证码
            require_once __DIR__ . '/SmsService.php';
            $smsService = new SmsService();
            
            if ($event === 'unbind') {
                // 解绑时验证当前绑定的手机号
                $currentUser = $this->redisModel->hGetAll("user:{$userId}");
                $verifyMobile = $currentUser['mobile'] ?? '';
            } else {
                // 绑定时验证新手机号
                $verifyMobile = $mobile;
            }
            $smsVerifyResult = $smsService->verifySmsCode($verifyMobile, $smsCode, $event);
            if (!$smsVerifyResult['valid']) {
                return [
                    'success' => false,
                    'message' => $smsVerifyResult['message'],
                    'code' => 400
                ];
            }
            
            // 如果是解绑操作
            if ($event === 'unbind' || $mobile === '') {
                // 获取当前用户信息，清除旧的手机号映射
                $currentUser = $this->redisModel->hGetAll("user:{$userId}");
                if (!empty($currentUser['mobile'])) {
                    $this->redisModel->delete("user:mobile:{$currentUser['mobile']}");
                }
                
                // 更新Redis中的用户信息（清空手机号）
                $updateData = [
                    'mobile' => '',
                    'updatetime' => time(),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $result = $this->redisModel->hMSet("user:{$userId}", $updateData);
                
                if (!$result) {
                    return [
                        'success' => false,
                        'message' => '解绑手机号失败',
                        'code' => 500
                    ];
                }
                
                // 清除用户信息缓存
                $this->redisModel->delete("user_info:{$userId}");
                
                // 获取更新后的用户信息
                $userInfo = $this->redisModel->hGetAll("user:{$userId}");
                unset($userInfo['password']);
                unset($userInfo['salt']);
                
                // 发送用户更新数据到队列进行异步处理
                $this->publishUserUpdateToQueue($userId, $updateData);
                
                // 发送用户操作日志数据到队列进行异步处理
                $this->userLogService->publishUserLogToQueue([
                    'title' => '解绑手机',
                    'user_id' => $userId,
                    'username' => $userInfo['username'],
                    'content' => json_encode(
                        ['mobile' => "{$currentUser['mobile']}", 'message' => '已解绑'],
                        JSON_UNESCAPED_UNICODE
                    ),
                ]);
                
                return [
                    'success' => true,
                    'message' => '解绑手机成功',
                    'data' => $userInfo,
                    'code' => 200
                ];
            }
            
            // 绑定新手机号的逻辑
            // 手机号格式验证
            if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
                return [
                    'success' => false,
                    'message' => '手机号格式不正确',
                    'code' => 400
                ];
            }
            
            // 检查手机号是否已被其他用户使用
            $existingUserId = $this->redisModel->get("user:mobile:{$mobile}");
            if ($existingUserId && $existingUserId != $userId) {
                return [
                    'success' => false,
                    'message' => '该手机号已被其他用户使用',
                    'code' => 409
                ];
            }
            
            // 获取当前用户信息，清除旧的手机号映射
            $currentUser = $this->redisModel->hGetAll("user:{$userId}");

            if (!empty($currentUser['mobile'])) {
                return [
                    'success' => false,
                    'message' => '请先解绑，在重新绑定',
                    'code' => 409
                ];
//                $this->redisModel->delete("user:mobile:{$currentUser['mobile']}");
            }
            
            // 更新Redis中的用户信息
            $updateData = [
                'mobile' => $mobile,
                'updatetime' => time(),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->redisModel->hMSet("user:{$userId}", $updateData);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => '绑定手机号失败',
                    'code' => 500
                ];
            }
            
            // 设置新的手机号映射
            $this->redisModel->set("user:mobile:{$mobile}", $userId);
            
            // 清除用户信息缓存
            $this->redisModel->delete("user_info:{$userId}");
            
            // 获取更新后的用户信息
            $userInfo = $this->redisModel->hGetAll("user:{$userId}");
            unset($userInfo['password']);
            unset($userInfo['salt']);
            
            // 发送用户更新数据到队列进行异步处理
            $this->publishUserUpdateToQueue($userId, $updateData);
            
            // 发送用户操作日志数据到队列进行异步处理
            $this->userLogService->publishUserLogToQueue([
                'title' => '绑定手机',
                'user_id' => $userId,
                'username' => $userInfo['username'],
                'content' => json_encode($updateData, JSON_UNESCAPED_UNICODE),
            ]);
            
            return [
                'success' => true,
                'message' => '绑定手机成功',
                'data' => $userInfo,
                'code' => 200
            ];
            
        } catch (Exception $e) {
            error_log("[UserService] 手机号操作失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '操作失败，请稍后重试',
                'code' => 500
            ];
        }
    }
    
    /**
     * 修改用户邮箱
     */
    public function updateEmail($userId, $email, $event = 'bind') {
        try {
            // 获取当前用户信息，清除旧的邮箱映射
            $currentUser = $this->redisModel->hGetAll("user:{$userId}");
            if (!empty($currentUser['email'])) {
                $this->redisModel->delete("user:email:{$currentUser['email']}");
            }

            // 根据event参数判断是绑定还是解绑操作
            if ($event === 'unbind') {

                // 更新Redis中的用户信息（清空邮箱）
                $updateData = [
                    'email' => '',
                    'updatetime' => time(),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $result = $this->redisModel->hMSet("user:{$userId}", $updateData);
                
                if (!$result) {
                    return [
                        'success' => false,
                        'message' => '解绑邮箱失败',
                        'code' => 500
                    ];
                }

                // 清除用户信息缓存
                $this->redisModel->delete("user_info:{$userId}");
                
                // 获取更新后的用户信息
                $userInfo = $this->redisModel->hGetAll("user:{$userId}");
                unset($userInfo['password']);
                unset($userInfo['salt']);
                
                // 发送用户更新数据到队列进行异步处理
                $this->publishUserUpdateToQueue($userId, $updateData);
                // 发送用户操作日志数据到队列进行异步处理
                $updateData['email'] = $email;
                $this->userLogService->publishUserLogToQueue([
                    'title' => '解绑邮箱',
                    'user_id' => $userId,
                    'username' => $userInfo['username'],
                    'content' => json_encode($updateData, JSON_UNESCAPED_UNICODE),
                ]);
                
                return [
                    'success' => true,
                    'message' => '邮箱解绑成功',
                    'data' => $userInfo,
                    'code' => 200
                ];
            }
            
            // 邮箱格式验证
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => '邮箱格式不正确',
                    'code' => 400
                ];
            }
            
            // 邮箱长度验证
            if (strlen($email) > 100) {
                return [
                    'success' => false,
                    'message' => '邮箱长度不能超过100个字符',
                    'code' => 400
                ];
            }
            
            // 检查邮箱是否已被其他用户使用
            $existingUserId = $this->redisModel->get("user:email:{$email}");
            if ($existingUserId && $existingUserId != $userId) {
                return [
                    'success' => false,
                    'message' => '该邮箱已被其他用户使用',
                    'code' => 409
                ];
            }
            
            // 更新Redis中的用户信息
            $updateData = [
                'email' => $email,
                'updatetime' => time(),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->redisModel->hMSet("user:{$userId}", $updateData);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => '修改邮箱失败',
                    'code' => 500
                ];
            }
            
            // 设置新的邮箱映射
            $this->redisModel->set("user:email:{$email}", $userId);
            
            // 清除用户信息缓存
            $this->redisModel->delete("user_info:{$userId}");
            
            // 获取更新后的用户信息
            $userInfo = $this->redisModel->hGetAll("user:{$userId}");
            unset($userInfo['password']);
            unset($userInfo['salt']);
            // 发送用户注册数据到队列进行异步处理
            $this->publishUserUpdateToQueue($userId, $updateData);
            // 发送用户操作日志数据到队列进行异步处理
            $this->userLogService->publishUserLogToQueue([
                'title' => '绑定邮箱',
                'user_id' => $userId,
                'username' => $userInfo['username'],
                'content' => json_encode($updateData, JSON_UNESCAPED_UNICODE),
            ]);

            return [
                'success' => true,
                'message' => '邮箱绑定成功',
                'data' => $userInfo,
                'code' => 200
            ];
            
        } catch (Exception $e) {
            error_log("[UserService] 绑定邮箱失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '绑定邮箱失败，请稍后重试',
                'code' => 500
            ];
        }
    }
    
    /**
     * 发送用户注册数据到队列进行异步处理
     * 实现高复用性的队列集成
     * 
     * @param array $userData 用户注册数据
     * @return bool 发送是否成功
     */
    private function publishUserRegistrationToQueue($userData) {
        try {
            // 准备队列数据，移除敏感信息
            $queueData = $userData;

            // 发送到用户操作队列
            $result = $this->queueService->publishUserOperation(
                'insert',           // 操作类型：插入
                'jkpk_user',         // 目标表名
                $queueData,         // 用户数据
                $userData['id']     // 用户ID
            );
            
            if ($result) {
                error_log("[UserService] 用户注册数据已发送到队列: 用户ID {$userData['id']}");
            } else {
                error_log("[UserService] 用户注册数据发送到队列失败: 用户ID {$userData['id']}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("[UserService] 发送用户注册数据到队列异常: " . $e->getMessage());
            // 队列发送失败不影响注册流程，只记录日志
            return false;
        }
    }
    
    /**
     * 发送用户更新数据到队列进行异步处理
     * 可复用的用户数据更新队列方法
     * 
     * @param int $userId 用户ID
     * @param array $updateData 更新数据
     * @param string $action 更新动作标识
     * @return bool 发送是否成功
     */
    public function publishUserUpdateToQueue($userId, $updateData, $action = 'user_update') {
        try {
            // 准备队列数据
            $queueData = $updateData;

            // 发送到用户操作队列
            $result = $this->queueService->publishUserOperation(
                'update',           // 操作类型：更新
                'jkpk_user',         // 目标表名
                $queueData,         // 更新数据
                $userId             // 用户ID
            );
            
            if ($result) {
                error_log("[UserService] 用户更新数据已发送到队列: 用户ID {$userId}, 动作: {$action}");
            } else {
                error_log("[UserService] 用户更新数据发送到队列失败: 用户ID {$userId}, 动作: {$action}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("[UserService] 发送用户更新数据到队列异常: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 用户退出登录
     * 清除用户会话信息
     */
    public function logout($userId) {
        try {
            // 参数验证
            if (empty($userId)) {
                return [
                    'success' => false,
                    'message' => '用户ID不能为空',
                    'code' => 400
                ];
            }
            
            // 获取用户信息用于日志记录
            $user = $this->redisModel->hGetAll("user:{$userId}");
            $username = $user['username'] ?? '';
            
            // 清除用户会话
            $sessionCleared = $this->redisModel->deleteUserSession($userId);
            
            if (!$sessionCleared) {
                error_log("[UserService] 清除用户会话失败: 用户ID {$userId}");
            }
            
            // 发送用户操作日志数据到队列进行异步处理
            $this->userLogService->publishUserLogToQueue([
                'title' => '退出登录',
                'user_id' => $userId,
                'username' => $username,
                'content' => json_encode([
                    'logout_time' => date('Y-m-d H:i:s'),
                    'logout_ip' => $this->getClientIP()
                ], JSON_UNESCAPED_UNICODE),
            ]);
            
            return [
                'success' => true,
                'message' => '退出登录成功',
                'code' => 200
            ];
            
        } catch (Exception $e) {
            error_log("[UserService] 退出登录失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '退出登录失败，请稍后重试',
                'code' => 500
            ];
        }
    }
    
    /**
     * 手机短信登录
     * 支持未注册手机号自动创建账号并绑定手机号
     * 
     * @param string $mobile 手机号
     * @param string $smsCode 短信验证码
     * @param string $captchaCode 图形验证码
     * @param string $sessionId 验证码会话ID
     * @return array 登录结果
     */
    public function mobileLogin($mobile, $smsCode, $captchaCode, $sessionId) {
        try {
            // 参数验证
            if (empty($mobile) || empty($smsCode)) {
                return [
                    'success' => false,
                    'message' => '手机号和短信验证码不能为空',
                    'code' => 400
                ];
            }
            
            // 验证手机号格式
            if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
                return [
                    'success' => false,
                    'message' => '手机号格式不正确',
                    'code' => 400
                ];
            }
            
            // 检查登录失败次数限制
            if ($this->redisModel->isLoginBlocked($mobile)) {
                return [
                    'success' => false,
                    'message' => '登录失败次数过多，请稍后再试',
                    'code' => 429
                ];
            }

            // 查找是否已有该手机号的用户
            $userId = $this->redisModel->get("user:mobile:{$mobile}");
            $user = null;
            $isNewUser = false;
            
            if ($userId) {
                // 已有用户，获取用户信息
                $user = $this->redisModel->hGetAll("user:{$userId}");
                
                if (!$user || empty($user)) {
                    // Redis中没有用户信息，尝试从用户名索引查找
                    $userId = $this->redisModel->get("user:username:{$mobile}");
                    if ($userId) {
                        $user = $this->redisModel->hGetAll("user:{$userId}");
                    }
                }
            } else {
                // 尝试通过用户名查找（可能手机号作为用户名注册）
                $userId = $this->redisModel->get("user:username:{$mobile}");
                if ($userId) {
                    $user = $this->redisModel->hGetAll("user:{$userId}");
                }
            }
            
            // 如果没有找到用户，创建新用户
            if (!$user || empty($user)) {
                $isNewUser = true;
                
                // 生成新用户ID
                $userId = $this->redisModel->incr('user:next_id');
                
                // 生成盐值
                $salt = $this->generateSalt();
                
                // 准备新用户数据
                $currentTime = time();
                $user = [
                    'id' => $userId,
                    'group_id' => 0,
                    'username' => $mobile,
                    'nickname' => $mobile,
                    'password' => md5($mobile . '5488' . $salt), // 默认密码为手机号
                    'salt' => $salt,
                    'email' => '',
                    'mobile' => $mobile,
                    'avatar' => '',
                    'level' => 0,
                    'gender' => 0,
                    'birthday' => null,
                    'bio' => '',
                    'money' => 0.00,
                    'score' => 0,
                    'successions' => 1,
                    'maxsuccessions' => 1,
                    'prevtime' => null,
                    'logintime' => $currentTime,
                    'loginip' => $this->getClientIP(),
                    'loginfailure' => 0,
                    'loginfailuretime' => null,
                    'joinip' => $this->getClientIP(),
                    'jointime' => $currentTime,
                    'createtime' => $currentTime,
                    'updatetime' => $currentTime,
                    'token' => '',
                    'verification' => '',
                    'status' => 'normal'
                ];
                
                // 保存用户到Redis
                $this->redisModel->hMSet("user:{$userId}", $user);
                $this->redisModel->set("user:username:{$mobile}", $userId);
                $this->redisModel->set("user:mobile:{$mobile}", $userId);
                
                // 发送新用户数据到队列进行数据库同步
                $this->publishUserRegistrationToQueue($user);
                
            } else {
                // 现有用户，检查是否需要绑定手机号
                if (empty($user['mobile']) || $user['mobile'] !== $mobile) {
                    // 更新手机号绑定
                    $currentTime = time();
                    $updateData = [
                        'mobile' => $mobile,
                        'prevtime' => $user['logintime'] ?? null,
                        'logintime' => $currentTime,
                        'loginip' => $this->getClientIP(),
                        'updatetime' => $currentTime
                    ];
                    
                    // 更新Redis
                    $this->redisModel->hMSet("user:{$userId}", $updateData);
                    $this->redisModel->set("user:mobile:{$mobile}", $userId);
                    
                    // 更新用户数据
                    $user['mobile'] = $mobile;
                    $user['updatetime'] = $updateData['updatetime'];
                    
                    // 发送更新数据到队列
                    $this->publishUserUpdateToQueue($userId, $updateData, 'mobile_bind');
                }
            }
            
            // 检查用户状态
            if ($user['status'] == 'deny') {
                return [
                    'success' => false,
                    'message' => '账户已被禁用，请联系客服',
                    'code' => 403
                ];
            }
            
            // 登录成功，清除失败记录
            $this->redisModel->clearLoginFailure($mobile);

            // 生成登录令牌
            $token = $this->generateToken($userId);
            $user['user_id'] = intval($userId);
            
            // 缓存用户会话
            $sessionData = [
                'user_id' => $userId,
                'username' => $user['username'],
                'mobile' => $mobile,
                'login_time' => $currentTime,
                'login_ip' => $this->getClientIP(),
                'login_type' => 'mobile'
            ];
            $this->redisModel->setUserSession($userId, $sessionData);

            // 移除敏感信息
            unset($user['password']);
            unset($user['salt']);
            
            // 格式化头像URL
            $user['avatar'] = $this->formatAvatarUrl($user['avatar'] ?? '');
            
            // 发送用户操作日志数据到队列进行异步处理
            $logTitle = $isNewUser ? '短信注册/登录' : '短信登录';
            $this->userLogService->publishUserLogToQueue([
                'title' => $logTitle,
                'user_id' => $userId,
                'username' => $user['username'],
                'content' => json_encode([
                    'mobile' => $mobile,
                    'sms_code' => $smsCode,
                    'is_new_user' => $isNewUser
                ], JSON_UNESCAPED_UNICODE),
            ]);
            
            return [
                'success' => true,
                'message' => '登录成功',
                'data' => [
                    'user_id' => $userId,
                    'token' => $token,
                    'user_info' => $user
                ],
                'code' => 200
            ];
            
        } catch (Exception $e) {
            error_log("[UserService] 手机短信登录失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '登录失败，请稍后重试',
                'code' => 500
            ];
        }
    }
    
    /**
     * 验证用户token
     */
    public function validateToken($userId, $token) {
        try {
            // 解码token
            $decoded = base64_decode($token);
            if (!$decoded) {
                return false;
            }
            
            $payload = json_decode($decoded, true);
            if (!$payload || !isset($payload['user_id']) || !isset($payload['timestamp'])) {
                return false;
            }
            
            // 验证用户ID是否匹配
            if ($payload['user_id'] != $userId) {
                return false;
            }
            
            // 验证token是否过期（24小时有效期）
            $tokenAge = time() - $payload['timestamp'];
            if ($tokenAge > 86400) { // 24小时 = 86400秒
                return false;
            }
            
            // 验证用户是否存在且状态正常
            $user = $this->redisModel->hGetAll("user:{$userId}");
            if (!$user || empty($user) || $user['status'] == 'deny') {
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("[UserService] Token验证失败: " . $e->getMessage());
            return false;
        }
    }
}