<?php

/**
 * 队列日志数据访问模型类
 * 提供队列日志相关的数据库操作接口
 */

require_once 'MySQLModel.php';
require_once __DIR__ . '/../db/QueueLogTable.php';

class QueueLogModel {
    private $mysqlModel;
    
    // 表名常量
    private const TABLE_QUEUE_LOG = QueueLogTable::TABLE_NAME;
    
    // 主键字段
    private $pk = 'id';
    
    public function __construct($testMode = false) {
        $this->mysqlModel = MySQLModel::getInstance($testMode);
    }
    
    /**
     * 创建队列日志记录
     */
    public function createQueueLog($logData) {
        // 使用QueueLogTable填充默认值
        $logData = QueueLogTable::fillDefaults($logData);
        return $this->mysqlModel->insert($this->mysqlModel->getTableName(self::TABLE_QUEUE_LOG), $logData);
    }
    
    /**
     * 根据ID获取队列日志信息
     */
    public function getQueueLogById($logId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QUEUE_LOG);
        $sql = "SELECT * FROM {$tableName} WHERE {$this->pk} = :log_id";
        return $this->mysqlModel->queryOne($sql, ['log_id' => $logId]);
    }
    
    /**
     * 根据用户ID获取队列日志列表
     */
    public function getQueueLogsByUserId($userId, $limit = 50, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QUEUE_LOG);
        $sql = "SELECT * FROM {$tableName} WHERE user_id = :user_id ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 根据类型获取队列日志列表
     */
    public function getQueueLogsByType($type, $limit = 50, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QUEUE_LOG);
        $sql = "SELECT * FROM {$tableName} WHERE type = :type ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'type' => $type,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 根据状态获取队列日志列表
     */
    public function getQueueLogsByStatus($status, $limit = 50, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QUEUE_LOG);
        $sql = "SELECT * FROM {$tableName} WHERE status = :status ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'status' => $status,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 获取未处理的队列日志列表
     */
    public function getPendingQueueLogs($limit = 50, $offset = 0) {
        return $this->getQueueLogsByStatus(QueueLogTable::STATUS_PENDING, $limit, $offset);
    }
    
    /**
     * 更新队列日志状态
     */
    public function updateQueueLogStatus($logId, $status) {
        return $this->mysqlModel->update(
            $this->mysqlModel->getTableName(self::TABLE_QUEUE_LOG),
            ['status' => $status],
            "{$this->pk} = :log_id", ['log_id' => $logId]
        );
    }
    
    /**
     * 更新队列日志信息
     */
    public function updateQueueLog($logId, $logData) {
        return $this->mysqlModel->update(
            $this->mysqlModel->getTableName(self::TABLE_QUEUE_LOG),
            $logData,
            "{$this->pk} = :log_id", ['log_id' => $logId]
        );
    }
    
    /**
     * 删除队列日志记录
     */
    public function deleteQueueLog($logId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QUEUE_LOG);
        $sql = "DELETE FROM {$tableName} WHERE {$this->pk} = :log_id";
        return $this->mysqlModel->execute($sql, ['log_id' => $logId]);
    }

    /**
     * 统计队列日志数量
     */
    public function countQueueLogs($conditions = []) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QUEUE_LOG);
        $sql = "SELECT COUNT(*) as count FROM {$tableName}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $result = $this->mysqlModel->queryOne($sql, $params);
        return $result['count'];
    }
}