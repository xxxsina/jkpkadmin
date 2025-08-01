<?php
/**
 * MySQL数据访问模型类
 * 提供统一的MySQL数据库操作接口
 */

class MySQLModel {
    private static $instance = null;
    private $pdo = null;
    private $config = [];
    private $heartbeatInterval = 30; // 心跳间隔（秒）
    private $maxRetries = 3; // 最大重试次数
    private $retryDelay = 1; // 初始重试延迟（秒）
    private $lastHeartbeat = 0; // 上次心跳时间
    private $connectionErrors = []; // 连接错误记录
    
    /**
     * 私有构造函数，实现单例模式
     */
    private function __construct($testMode = false) {
        $this->config = require __DIR__ . '/../config/database.php';
        if (!$testMode) {
            $this->connect();
        }
    }
    
    /**
     * 获取单例实例
     */
    public static function getInstance($testMode = false) {
        if (self::$instance === null) {
            self::$instance = new self($testMode);
        }
        return self::$instance;
    }
    
    /**
     * 建立数据库连接（带重试机制）
     */
    private function connect() {
        $retryCount = 0;
        $delay = $this->retryDelay;
        
        while ($retryCount < $this->maxRetries) {
            try {
                $config = $this->config['mysql'];
                $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
                
                $this->pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
                
                // 设置字符集
                $this->pdo->exec("SET NAMES {$config['charset']}");
                
                // 连接成功，清空错误记录
                $this->connectionErrors = [];
                $this->lastHeartbeat = time();
                
                error_log("[MySQLModel] 数据库连接成功");
                
                // 启动心跳线程
                $this->startHeartbeat();
                
                return;
            } catch (PDOException $e) {
                $retryCount++;
                $errorMsg = "[MySQLModel] 数据库连接失败 (尝试 {$retryCount}/{$this->maxRetries}): " . $e->getMessage();
                error_log($errorMsg);
                
                // 记录错误
                $this->connectionErrors[] = [
                    'time' => time(),
                    'attempt' => $retryCount,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ];
                
                if ($retryCount < $this->maxRetries) {
                    error_log("[MySQLModel] {$delay}秒后重试连接...");
                    sleep($delay);
                    $delay *= 2; // 指数退避
                } else {
                    error_log("[MySQLModel] 达到最大重试次数，连接失败");
                    throw new Exception("数据库连接失败: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * 启动心跳线程
     */
    private function startHeartbeat() {
        // 在PHP中，我们不能创建真正的线程，但可以在每次操作前检查心跳
        // 这里记录启动心跳的时间
        $this->lastHeartbeat = time();
        error_log("[MySQLModel] 心跳监控已启动，间隔: {$this->heartbeatInterval}秒");
    }
    
    /**
     * 检查心跳并重连（如果需要）
     */
    private function checkHeartbeat() {
        $currentTime = time();
        
        // 如果距离上次心跳超过间隔时间，执行心跳检查
        if ($currentTime - $this->lastHeartbeat >= $this->heartbeatInterval) {
            try {
                // 执行简单的查询来检查连接
                $this->pdo->query('SELECT 1');
                $this->lastHeartbeat = $currentTime;
                error_log("[MySQLModel] 心跳检查正常");
            } catch (PDOException $e) {
                error_log("[MySQLModel] 心跳检查失败，尝试重连: " . $e->getMessage());
                $this->reconnect();
            }
        }
    }
    
    /**
     * 重新连接数据库
     */
    private function reconnect() {
        error_log("[MySQLModel] 开始重新连接数据库...");
        $this->pdo = null;
        $this->connect();
    }
    
    /**
     * 获取连接错误历史
     */
    public function getConnectionErrors() {
        return $this->connectionErrors;
    }
    
    /**
     * 设置心跳间隔
     */
    public function setHeartbeatInterval($interval) {
        $this->heartbeatInterval = max(10, $interval); // 最小10秒
        error_log("[MySQLModel] 心跳间隔已设置为: {$this->heartbeatInterval}秒");
    }
    
    /**
     * 设置重试参数
     */
    public function setRetryConfig($maxRetries, $retryDelay) {
        $this->maxRetries = max(1, $maxRetries);
        $this->retryDelay = max(1, $retryDelay);
        error_log("[MySQLModel] 重试配置已更新: 最大重试{$this->maxRetries}次，初始延迟{$this->retryDelay}秒");
    }
    
    /**
     * 获取连接状态信息
     */
    public function getConnectionStatus() {
        return [
            'connected' => $this->pdo !== null,
            'last_heartbeat' => $this->lastHeartbeat,
            'heartbeat_interval' => $this->heartbeatInterval,
            'max_retries' => $this->maxRetries,
            'retry_delay' => $this->retryDelay,
            'error_count' => count($this->connectionErrors),
            'last_error' => !empty($this->connectionErrors) ? end($this->connectionErrors) : null
        ];
    }
    
    /**
     * 手动触发心跳检查
     */
    public function forceHeartbeat() {
        $this->lastHeartbeat = 0; // 强制触发心跳检查
        $this->checkHeartbeat();
    }
    
    /**
     * 获取PDO实例（带心跳检查）
     */
    public function getPDO() {
        $this->checkHeartbeat();
        return $this->pdo;
    }
    
    /**
     * 获取表前缀
     */
    public function getTablePrefix() {
        return $this->config['mysql']['table_prefix'] ?? '';
    }
    
    /**
     * 获取带前缀的表名
     */
    public function getTableName($tableName) {
        return $this->getTablePrefix() . $tableName;
    }
    
    /**
     * 执行查询语句
     */
    public function query($sql, $params = []) {
        $this->checkHeartbeat();
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("[MySQLModel] 查询失败: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("查询失败");
        }
    }
    
    /**
     * 执行查询并返回单条记录
     */
    public function queryOne($sql, $params = []) {
        $this->checkHeartbeat();
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("[MySQLModel] 查询失败: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("查询失败");
        }
    }
    
    /**
     * 执行插入语句
     */
    public function insert($table, $data) {
        $this->checkHeartbeat();
        try {
            $fields = array_keys($data);
            $quotedFields = array_map(function($field) { return "`{$field}`"; }, $fields);
            $placeholders = ':' . implode(', :', $fields);
            $sql = "INSERT INTO {$table} (" . implode(', ', $quotedFields) . ") VALUES ({$placeholders})";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($data);

            if ($result) {
                return $this->pdo->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("[MySQLModel] 插入失败: " . $e->getMessage() . " Table: " . $table);
            throw new Exception("插入失败");
        }
    }
    
    /**
     * 执行更新语句
     */
    public function update($table, $data, $where, $whereParams = []) {
        $this->checkHeartbeat();
        try {
            $setClause = [];
            foreach (array_keys($data) as $field) {
                $setClause[] = "`{$field}` = :{$field}";
            }
            
            $sql = "UPDATE {$table} SET " . implode(', ', $setClause) . " WHERE {$where}";
            $params = array_merge($data, $whereParams);
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("[MySQLModel] 更新失败: " . $e->getMessage() . " Table: " . $table);
            throw new Exception("更新失败");
        }
    }
    
    /**
     * 执行删除语句
     */
    public function delete($table, $where, $whereParams = []) {
        $this->checkHeartbeat();
        try {
            $sql = "DELETE FROM {$table} WHERE {$where}";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($whereParams);
        } catch (PDOException $e) {
            error_log("[MySQLModel] 删除失败: " . $e->getMessage() . " Table: " . $table);
            throw new Exception("删除失败");
        }
    }
    
    /**
     * 开始事务
     */
    public function beginTransaction() {
        $this->checkHeartbeat();
        return $this->pdo->beginTransaction();
    }
    
    /**
     * 提交事务
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * 回滚事务
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * 执行SQL语句（用于DELETE、UPDATE等不需要返回结果集的操作）
     */
    public function execute($sql, $params = []) {
        $this->checkHeartbeat();
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("[MySQLModel] 执行失败: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("执行失败");
        }
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