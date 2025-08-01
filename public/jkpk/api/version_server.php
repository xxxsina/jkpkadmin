<?php
/**
 * 版本更新API接口
 * 用于Android应用版本检查和更新
 * 
 * 接口地址: http://shop.blcwg.com/version.php
 * 请求方式: POST
 * 返回格式: JSON
 */

require_once __DIR__ . '/../utils/ApiUtils.php';
require_once __DIR__ . '/../models/RedisModel.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../services/UserLogService.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * 版本信息配置
 * 在实际项目中，这些信息应该存储在数据库中
 */
class VersionConfig {
    // 当前最新版本信息
    const LATEST_VERSION = [
        'versionCode' => 1,
        'versionName' => '1.0.0',
        'updateMessage' => "新版本更新内容： 优化改进：\n• 修复已知问题\n• 提升应用性能\n• 优化用户界面",
        'downloadUrl' => 'http://shbcdn.blcwg.com/downloads/jiankangpaika_v%s-release.apk',
        'forceUpdate' => true,
        'fileSize' => '39.60MB',
        'updateTime' => '2025-07-14 17:00:00'
    ];
    
    // 强制更新的最低版本号
    const MIN_FORCE_UPDATE_VERSION = 1;
}

/**
 * 版本更新API类
 */
class VersionUpdateAPI {
    private $redisModel;
    private $userService;
    private $userLogService;
    public function __construct() {
        $this->redisModel = RedisModel::getInstance();
    }
    
    /**
     * 获取客户端IP地址
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
      * 检查用户当日是否首次访问APP
      * @param int $userId 用户ID
      * @return bool 是否首次访问
      */
     private function isFirstVisitToday($userId) {
         $today = date('Y-m-d');
         $lastVisitDate = $this->redisModel->get("user_last_visit:{$userId}");
         
         if (empty($lastVisitDate) || $lastVisitDate !== $today) {
             // 计算当日剩余时间到午夜的秒数
             $endOfDay = strtotime($today . ' 23:59:59');
             $currentTime = time();
             $ttl = $endOfDay - $currentTime + 1; // +1确保过了午夜
             
             // 记录今日访问，设置过期时间为当日结束
             $this->redisModel->set("user_last_visit:{$userId}", $today, $ttl);
             return true;
         }
         
         return false;
     }
    
    /**
     * 更新用户信息到Redis
     * @param int $userId 用户ID
     * @param string $versionName 版本名称
     */
    private function updateUserInfo($userId, $versionName) {
        $currentTime = time();
        
        // 获取用户当前信息
        $user = $this->redisModel->hGetAll("user:{$userId}");
        
        if ($user && !empty($user)) {
            $updateData = [
                'prevtime' => $user['logintime'] ?? null,
                'logintime' => $currentTime,
                'loginip' => $this->getClientIP(),
                'updatetime' => $currentTime,
                'version_name' => $versionName,
            ];
            // 更新用户信息
            $this->redisModel->hMSet("user:{$userId}", $updateData);
            // 发送用户数据到队列进行异步处理
            $this->userService = new UserService();
            $this->userService->publishUserUpdateToQueue($userId, $updateData);
            // 发送用户操作日志数据到队列进行异步处理
            $this->userLogService = UserLogService::getInstance();
            $this->userLogService->publishUserLogToQueue([
                'title'     => '刷新登录和版本',
                'user_id'   => $userId,
                'username'  => $user['username'],
                'content'   => json_encode(
                    array('username' => $user['username'], 'version_name' => $versionName),
                    JSON_UNESCAPED_UNICODE
                ),
            ]);

            error_log("[VersionAPI] 用户 {$userId} 首次访问APP，已更新用户信息");
        }
    }
    
    /**
     * 检查版本更新
     * @param int $currentVersionCode 客户端当前版本号
     * @param string $versionName 版本名称（必传）
     * @param int $userId 用户ID（非必传）
     * @return array 响应数据
     */
    public function checkUpdate($currentVersionCode = 0, $versionName = '', $userId = null) {
        if ($currentVersionCode == -1) {
            header('Content-Type: text/html; charset=utf-8');
        }
        try {
            // 如果同时提供了versionName和user_id，检查是否首次访问
            if (!empty($versionName) && !empty($userId)) {
                if ($this->isFirstVisitToday($userId)) {
                    $this->updateUserInfo($userId, $versionName);
                }
            }
            // 获取最新版本信息
            $latestVersion = VersionConfig::LATEST_VERSION;
            
            // 检查是否需要更新
            if ($currentVersionCode >= $latestVersion['versionCode']) {
                // 当前已是最新版本
                return $this->successResponse('当前已是最新版本', null);
            }
            
            // 检查是否需要强制更新
            if ($currentVersionCode <= VersionConfig::MIN_FORCE_UPDATE_VERSION) {
                $latestVersion['forceUpdate'] = true;
                $latestVersion['updateMessage'] = "⚠️ 重要更新\n\n" . $latestVersion['updateMessage'] . "\n\n📢 此版本为重要安全更新，请立即升级！";
            }

            // 实时填充数据
            $latestVersion['fileSize'] = $this->getApkFileSize($latestVersion['versionName']);
            $latestVersion['downloadUrl'] = sprintf("{$latestVersion['downloadUrl']}", $latestVersion['versionName']);
            $latestVersion['updateTime'] = date("Y-m-d H:i:00");

            // 记录更新检查日志
            $this->logUpdateCheck($currentVersionCode, $latestVersion['versionCode']);
            
            return $this->successResponse('发现新版本', $latestVersion);
            
        } catch (Exception $e) {
            return $this->errorResponse('服务器内部错误: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取版本历史记录
     * @return array 版本历史列表
     */
    public function getVersionHistory() {
        // 在实际项目中，这些数据应该从数据库获取
        $versionHistory = [
            [
                'versionCode' => 2,
                'versionName' => '1.1.0',
                'releaseDate' => '2024-01-15',
                'updateMessage' => '新增运动数据统计功能，优化签到体验',
                'fileSize' => $this->getApkFileSize('1.1.0')
            ],
            [
                'versionCode' => 1,
                'versionName' => '1.0.0',
                'releaseDate' => '2024-01-01',
                'updateMessage' => '首个正式版本发布',
                'fileSize' => $this->getApkFileSize('1.0.0')
            ]
        ];
        
        return $this->successResponse('获取成功', $versionHistory);
    }
    
    /**
     * 获取APK文件大小
     * @param string $versionName 版本名称
     * @return string 格式化的文件大小
     */
    private function getApkFileSize($versionName) {
        // 构建APK文件路径
        $apkPath = __DIR__ . "/../downloads/jiankangpaika_v{$versionName}-release.apk";
        
        // 检查文件是否存在
        if (!file_exists($apkPath)) {
            return "0B";
        }
        
        // 获取文件大小（字节）
        $fileSizeBytes = filesize($apkPath);
        
        // 转换为可读格式
        return $this->formatFileSize($fileSizeBytes);
    }
    
    /**
     * 格式化文件大小
     * @param int $bytes 字节数
     * @return string 格式化的文件大小
     */
    private function formatFileSize($bytes) {
        if ($bytes == 0) {
            return '0B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));
        
        return sprintf('%.2f%s', $bytes / pow(1024, $factor), $units[$factor]);
    }
    
    /**
     * 成功响应
     * @param string $message 响应消息
     * @param mixed $data 响应数据
     * @return array
     */
    private function successResponse($message, $data = null) {
        return [
            'code' => 200,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ];
    }
    
    /**
     * 错误响应
     * @param string $message 错误消息
     * @param int $code 错误码
     * @return array
     */
    private function errorResponse($message, $code = 500) {
        return [
            'code' => $code,
            'message' => $message,
            'data' => null,
            'timestamp' => time()
        ];
    }
    
    /**
     * 记录更新检查日志
     * @param int $currentVersion 当前版本
     * @param int $latestVersion 最新版本
     */
    private function logUpdateCheck($currentVersion, $latestVersion) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'need_update' => $currentVersion < $latestVersion
        ];
        
        // 在实际项目中，应该将日志写入数据库或日志文件
        // error_log(json_encode($logData), 3, '/var`/log/version_check.log');
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
// 主要处理逻辑
    try {
        // 获取POST请求参数
        $params = ApiUtils::getRequestParams(
            ['versionName'], // 必传参数
            ['versionCode' => 0, 'user_id' => null, 'action' => 'check'] // 可选参数
        );

        $api = new VersionUpdateAPI();

        // 根据action参数处理不同请求
        switch ($params['action']) {
            case 'check':
                $response = $api->checkUpdate(
                    intval($params['versionCode']),
                    $params['versionName'],
                    $params['user_id']
                );
                break;

            case 'history':
                $response = $api->getVersionHistory();
                break;

            default:
                $response = [
                    'code' => 400,
                    'message' => '不支持的操作类型',
                    'data' => null
                ];
                break;
        }

    } catch (Exception $e) {
        $response = [
            'code' => 500,
            'message' => '服务器内部错误',
            'data' => null
        ];

        // 记录错误日志
        error_log('Version API Error: ' . $e->getMessage());
    }

    // 输出JSON响应
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
/**
=== 版本更新API使用说明 ===

接口地址: http://shop.blcwg.com/version.php
请求方式: POST
请求格式: JSON

请求参数:
- versionName (string, 必传): 客户端版本名称，如 "1.0.6"
- versionCode (int, 可选): 客户端版本号，默认为 0
- user_id (int, 可选): 用户ID，用于记录用户访问信息
- action (string, 可选): 操作类型，默认为 "check"，可选值："check"、"history"

请求示例:
{
    "versionName": "1.0.6",
    "versionCode": 6,
    "user_id": 12345,
    "action": "check"
}

响应格式:
{
    "code": 200,
    "message": "发现新版本",
    "data": {
        "versionCode": 1,
        "versionName": "1.0.0",
        "updateMessage": "新版本更新内容...",
        "downloadUrl": "http://jiankangpaika.blcwg.com/jkpk/downloads/jiankangpaika_v1.0.7.apk",
        "forceUpdate": false,
        "fileSize": "39.60MB",
        "updateTime": "2025-01-14 17:00:00"
    },
    "timestamp": 1642147200
}

功能说明:
1. 版本检查: 比较客户端版本与服务器最新版本
2. 用户访问记录: 当同时提供versionName和user_id时，会检查用户当日是否首次访问APP
3. 首次访问处理: 如果是当日首次访问，会更新Redis中的用户信息，包括:
   - prevtime: 上次登录时间
   - logintime: 当前登录时间
   - loginip: 登录IP地址
   - updatetime: 更新时间
   - version_name: 版本名称

错误码说明:
- 200: 成功
- 400: 参数错误
- 405: 请求方法不允许（非POST请求）
- 500: 服务器内部错误

注意事项:
1. 必须使用POST请求
2. versionName参数为必传参数
3. user_id参数可选，但建议传递以便记录用户访问信息
4. 用户访问记录基于日期，每日首次访问会更新用户信息
**/

?>