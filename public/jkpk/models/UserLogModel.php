<?php

/**
 * 用户操作日志数据访问模型类
 * 提供用户操作日志相关的数据库操作接口
 */

require_once 'MySQLModel.php';
require_once __DIR__ . '/../db/UserLogTable.php';

class UserLogModel {
    private $mysqlModel;
    
    // 表名常量
    private const TABLE_USER_LOG = UserLogTable::TABLE_NAME;
    
    // 主键字段
    private $pk = 'id';
    
    public function __construct($testMode = false) {
        $this->mysqlModel = MySQLModel::getInstance($testMode);
    }
    
    /**
     * 创建用户操作日志
     */
    public function createUserLog($logData) {
        // 填充默认值
        $logData = UserLogTable::fillDefaults($logData);
        return $this->mysqlModel->insert($this->mysqlModel->getTableName(self::TABLE_USER_LOG), $logData);
    }
    
    /**
     * 根据日志ID获取日志信息
     */
    public function getUserLogById($logId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_USER_LOG);
        $sql = "SELECT * FROM {$tableName} WHERE {$this->pk} = :log_id";
        return $this->mysqlModel->queryOne($sql, ['log_id' => $logId]);
    }
    
    /**
     * 根据用户ID获取用户操作日志列表
     */
    public function getUserLogsByUserId($userId, $limit = 50, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_USER_LOG);
        $sql = "SELECT * FROM {$tableName} WHERE user_id = :user_id ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 根据用户名获取用户操作日志列表
     */
    public function getUserLogsByUsername($username, $limit = 50, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_USER_LOG);
        $sql = "SELECT * FROM {$tableName} WHERE username = :username ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'username' => $username,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 根据时间范围获取用户操作日志
     */
    public function getUserLogsByTimeRange($startTime, $endTime, $limit = 100, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_USER_LOG);
        $sql = "SELECT * FROM {$tableName} WHERE createtime >= :start_time AND createtime <= :end_time ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 根据IP地址获取操作日志
     */
    public function getUserLogsByIp($ip, $limit = 50, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_USER_LOG);
        $sql = "SELECT * FROM {$tableName} WHERE ip = :ip ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'ip' => $ip,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 获取用户操作日志总数
     */
    public function getUserLogCount($userId = null) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_USER_LOG);
        if ($userId) {
            $sql = "SELECT COUNT(*) as count FROM {$tableName} WHERE user_id = :user_id";
            $result = $this->mysqlModel->queryOne($sql, ['user_id' => $userId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM {$tableName}";
            $result = $this->mysqlModel->queryOne($sql);
        }
        return $result['count'];
    }
    
    /**
     * 删除指定时间之前的日志（用于日志清理）
     */
//    public function deleteLogsBefore($timestamp) {
//        $tableName = $this->mysqlModel->getTableName(self::TABLE_USER_LOG);
//        $sql = "DELETE FROM {$tableName} WHERE createtime < :timestamp";
//        return $this->mysqlModel->execute($sql, ['timestamp' => $timestamp]);
//    }
}
?>