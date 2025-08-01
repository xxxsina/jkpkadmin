<?php
/**
 * Redis数据访问模型类
 * 提供统一的Redis缓存操作接口
 */

class RedisModel {
    private static $instance = null;
    private $redis = null;
    private $config = [];
    
    /**
     * 私有构造函数，实现单例模式
     */
    private function __construct() {
        $this->config = require __DIR__ . '/../config/database.php';
        $this->connect();
    }
    
    /**
     * 获取单例实例
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 建立Redis连接
     */
    private function connect() {
        try {
            $this->redis = new Redis();
            $config = $this->config['redis'];
            
            // 连接Redis服务器
            $connected = $this->redis->connect($config['host'], $config['port'], $config['timeout']);
            
            if (!$connected) {
                throw new Exception("Redis连接失败");
            }
            
            // 设置密码（如果有）
            if (!empty($config['password'])) {
                $this->redis->auth($config['password']);
            }
            
            // 选择数据库
            $this->redis->select($config['database']);
            
            // 设置选项
            foreach ($config['options'] as $option => $value) {
                $this->redis->setOption($option, $value);
            }
            
            error_log("[RedisModel] Redis连接成功");
        } catch (Exception $e) {
            error_log("[RedisModel] Redis连接失败: " . $e->getMessage());
            throw new Exception("Redis连接失败");
        }
    }
    
    /**
     * 获取Redis实例
     */
    public function getRedis() {
        return $this->redis;
    }
    
    /**
     * 设置缓存
     */
    public function set($key, $value, $ttl = null) {
        try {
            if ($ttl === null) {
                $ttl = $this->config['cache']['default_ttl'];
            }
            
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            if ($ttl > 0) {
                return $this->redis->set($key, $value, $ttl);
            } else {
                return $this->redis->set($key, $value);
            }
        } catch (Exception $e) {
            error_log("[RedisModel] 设置缓存失败: " . $e->getMessage() . " Key: " . $key);
            return false;
        }
    }

    public function getUniqueRandom($fix, $userId)
    {
        // 唯一随机数  5秒
        $time = time();
        return "{$fix}_{$userId}_" . ($time - $time % 5);
    }

    public function verifyUniqueRandom($fix, $userId)
    {
        $key = $fix . "_" . $userId;
        $random = $this->getUniqueRandom($fix, $userId);
        $rand = $this->redis->get($key);
        if (empty($rand)) {
            $this->redis->set($key, $random);
        } elseif ($rand != $random) {
            $this->redis->set($key, $random);
        } else {
            return false;
        }

        return $random;
    }
    
    /**
     * 获取缓存
     */
    public function get($key) {
        try {
            $value = $this->redis->get($key);
            
            if ($value === false) {
                return null;
            }
            
            // 尝试解析JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            return $value;
        } catch (Exception $e) {
            error_log("[RedisModel] 获取缓存失败: " . $e->getMessage() . " Key: " . $key);
            return null;
        }
    }
    
    /**
     * 删除缓存
     */
    public function delete($key) {
        try {
            return $this->redis->del($key);
        } catch (Exception $e) {
            error_log("[RedisModel] 删除缓存失败: " . $e->getMessage() . " Key: " . $key);
            return false;
        }
    }
    
    /**
     * 删除缓存（别名方法）
     */
    public function del($key) {
        return $this->delete($key);
    }
    
    /**
     * Hash - 设置多个字段
     */
    public function hMSet($key, $data) {
        try {
            $processedData = [];
            foreach ($data as $field => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                $processedData[$field] = $value;
            }
            return $this->redis->hMSet($key, $processedData);
        } catch (Exception $e) {
            error_log("[RedisModel] Hash设置失败: " . $e->getMessage() . " Key: " . $key);
            return false;
        }
    }
    
    /**
     * Hash - 获取所有字段
     */
    public function hGetAll($key) {
        try {
            $data = $this->redis->hGetAll($key);
            if (empty($data)) {
                return [];
            }
            
            $result = [];
            foreach ($data as $field => $value) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $result[$field] = $decoded;
                } else {
                    $result[$field] = $value;
                }
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("[RedisModel] Hash获取失败: " . $e->getMessage() . " Key: " . $key);
            return [];
        }
    }
    
    /**
     * Hash - 设置单个字段
     */
    public function hSet($key, $field, $value) {
        try {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            return $this->redis->hSet($key, $field, $value);
        } catch (Exception $e) {
            error_log("[RedisModel] Hash字段设置失败: " . $e->getMessage() . " Key: " . $key);
            return false;
        }
    }
    
    /**
     * Hash - 获取单个字段
     */
    public function hGet($key, $field) {
        try {
            $value = $this->redis->hGet($key, $field);
            if ($value === false) {
                return null;
            }
            
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            return $value;
        } catch (Exception $e) {
            error_log("[RedisModel] Hash字段获取失败: " . $e->getMessage() . " Key: " . $key);
            return null;
        }
    }
    
    /**
     * Hash - 删除字段
     */
    public function hDel($key, $field) {
        try {
            return $this->redis->hDel($key, $field);
        } catch (Exception $e) {
            error_log("[RedisModel] Hash字段删除失败: " . $e->getMessage() . " Key: " . $key);
            return false;
        }
    }
    
    /**
     * Hash - 检查字段是否存在
     */
    public function hExists($key, $field) {
        try {
            return $this->redis->hExists($key, $field);
        } catch (Exception $e) {
            error_log("[RedisModel] Hash字段检查失败: " . $e->getMessage() . " Key: " . $key);
            return false;
        }
    }
    
    /**
     * 检查键是否存在
     */
    public function exists($key) {
        try {
            return $this->redis->exists($key);
        } catch (Exception $e) {
            error_log("[RedisModel] 检查键存在失败: " . $e->getMessage() . " Key: " . $key);
            return false;
        }
    }
    
    /**
     * 设置键的过期时间
     */
    public function expire($key, $ttl) {
        try {
            return $this->redis->expire($key, $ttl);
        } catch (Exception $e) {
            error_log("[RedisModel] 设置过期时间失败: " . $e->getMessage() . " Key: " . $key);
            return false;
        }
    }
    
    /**
     * 获取键的剩余生存时间
     */
    public function ttl($key) {
        try {
            return $this->redis->ttl($key);
        } catch (Exception $e) {
            error_log("[RedisModel] 获取TTL失败: " . $e->getMessage() . " Key: " . $key);
            return -1;
        }
    }
    
    /**
     * 批量设置
     */
    public function mset($data) {
        try {
            $processedData = [];
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                $processedData[$key] = $value;
            }
            return $this->redis->mset($processedData);
        } catch (Exception $e) {
            error_log("[RedisModel] 批量设置失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 批量获取
     */
    public function mget($keys) {
        try {
            $values = $this->redis->mget($keys);
            $result = [];
            
            foreach ($values as $index => $value) {
                if ($value !== false) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $result[$keys[$index]] = $decoded;
                    } else {
                        $result[$keys[$index]] = $value;
                    }
                } else {
                    $result[$keys[$index]] = null;
                }
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("[RedisModel] 批量获取失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 自增操作
     */
    public function incr($key, $value = 1) {
        try {
            if ($value === 1) {
                return $this->redis->incr($key);
            } else {
                return $this->redis->incrBy($key, $value);
            }
        } catch (Exception $e) {
            error_log("[RedisModel] 自增操作失败: " . $e->getMessage() . " Key: " . $key);
            return false;
        }
    }
    
    /**
     * 自减操作
     */
    public function decr($key, $value = 1) {
        try {
            if ($value === 1) {
                return $this->redis->decr($key);
            } else {
                return $this->redis->decrBy($key, $value);
            }
        } catch (Exception $e) {
            error_log("[RedisModel] 自减操作失败: " . $e->getMessage() . " Key: " . $key);
            return false;
        }
    }
    
    /**
     * 开始Redis事务
     */
    public function multi() {
        try {
            return $this->redis->multi();
        } catch (Exception $e) {
            error_log("[RedisModel] 开始事务失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 执行Redis事务
     */
    public function exec() {
        try {
            return $this->redis->exec();
        } catch (Exception $e) {
            error_log("[RedisModel] 执行事务失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 取消Redis事务
     */
    public function discard() {
        try {
            return $this->redis->discard();
        } catch (Exception $e) {
            error_log("[RedisModel] 取消事务失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 有序集合 - 添加成员
     */
    public function zAdd($key, $score, $member) {
        try {
            return $this->redis->zAdd($key, $score, $member);
        } catch (Exception $e) {
            error_log("[RedisModel] 有序集合添加失败: " . $e->getMessage() . " Key: " . $key);
            return false;
        }
    }
    
    /**
     * 有序集合 - 获取指定范围的成员（按分数排序）
     */
    public function zRange($key, $start, $end, $withScores = false) {
        try {
            return $this->redis->zRange($key, $start, $end, $withScores);
        } catch (Exception $e) {
            error_log("[RedisModel] 有序集合范围获取失败: " . $e->getMessage() . " Key: " . $key);
            return [];
        }
    }
    
    /**
     * 有序集合 - 获取指定范围的成员（按分数倒序排序）
     */
    public function zRevRange($key, $start, $end, $withScores = false) {
        try {
            return $this->redis->zRevRange($key, $start, $end, $withScores);
        } catch (Exception $e) {
            error_log("[RedisModel] 有序集合倒序范围获取失败: " . $e->getMessage() . " Key: " . $key);
            return [];
        }
    }
    
    /**
     * 有序集合 - 删除成员
     */
    public function zRem($key, $member) {
        try {
            return $this->redis->zRem($key, $member);
        } catch (Exception $e) {
            error_log("[RedisModel] 有序集合删除失败: " . $e->getMessage() . " Key: " . $key);
            return false;
        }
    }
    
    /**
     * 有序集合 - 获取成员数量
     */
    public function zCard($key) {
        try {
            return $this->redis->zCard($key);
        } catch (Exception $e) {
            error_log("[RedisModel] 有序集合计数失败: " . $e->getMessage() . " Key: " . $key);
            return 0;
        }
    }

    /**
     * 用户会话管理 - 设置用户会话
     */
    public function setUserSession($userId, $sessionData) {
        $key = "user_session:{$userId}";
        $ttl = $this->config['cache']['user_session_ttl'];
        return $this->set($key, $sessionData, $ttl);
    }
    
    /**
     * 用户会话管理 - 获取用户会话
     */
    public function getUserSession($userId) {
        $key = "user_session:{$userId}";
        return $this->get($key);
    }
    
    /**
     * 用户会话管理 - 删除用户会话
     */
    public function deleteUserSession($userId) {
        $key = "user_session:{$userId}";
        return $this->delete($key);
    }
    
    /**
     * 用户信息缓存 - 设置用户信息
     */
    public function setUserInfo($userId, $userInfo) {
        $key = "user_info:{$userId}";
        $ttl = $this->config['cache']['user_info_ttl'];
        return $this->set($key, $userInfo, $ttl);
    }
    
    /**
     * 用户信息缓存 - 获取用户信息
     */
    public function getUserInfo($userId) {
        $key = "user_info:{$userId}";
        return $this->get($key);
    }
    
    /**
     * 验证码管理 - 设置验证码
     */
    public function setVerificationCode($identifier, $code, $type = 'register') {
        $key = "verification_code:{$type}:{$identifier}";
        $ttl = $this->config['cache']['verification_code_ttl'];
        return $this->set($key, $code, $ttl);
    }
    
    /**
     * 验证码管理 - 获取验证码
     */
    public function getVerificationCode($identifier, $type = 'register') {
        $key = "verification_code:{$type}:{$identifier}";
        return $this->get($key);
    }
    
    /**
     * 验证码管理 - 删除验证码
     */
    public function deleteVerificationCode($identifier, $type = 'register') {
        $key = "verification_code:{$type}:{$identifier}";
        return $this->delete($key);
    }
    
    /**
     * 登录尝试限制 - 记录登录失败
     */
    public function recordLoginFailure($identifier) {
        $key = "login_failure:{$identifier}";
        $count = $this->incr($key);
        
        // 设置1小时过期
        if ($count === 1) {
            $this->expire($key, 3600);
        }
        
        return $count;
    }
    
    /**
     * 登录尝试限制 - 获取登录失败次数
     */
    public function getLoginFailureCount($identifier) {
        $key = "login_failure:{$identifier}";
        $count = $this->get($key);
        return $count ? (int)$count : 0;
    }
    
    /**
     * 登录尝试限制 - 清除登录失败记录
     */
    public function clearLoginFailure($identifier) {
        $key = "login_failure:{$identifier}";
        return $this->delete($key);
    }
    
    /**
     * 登录尝试限制 - 检查是否被阻止登录
     * @param string $identifier 用户标识符（用户名、邮箱或手机号）
     * @return bool 是否被阻止登录
     */
    public function isLoginBlocked($identifier) {
        $failureCount = $this->getLoginFailureCount($identifier);
        
        // 如果失败次数超过5次，则阻止登录
        return $failureCount >= 5;
    }
    
    /**
     * 防止克隆
     */
    private function __clone() {}
    
    /**
     * 防止反序列化
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}