<?php
/**
 * 客户消息数据访问模型类
 * 提供客户消息相关的数据库操作接口
 */

require_once 'MySQLModel.php';
require_once __DIR__ . '/../db/CustomerMessageTable.php';

class CustomerMessageModel {
    private $mysqlModel;
    
    // 表名常量
    private const TABLE_CUSTOMER_MESSAGE = CustomerMessageTable::TABLE_NAME;
    
    // 主键字段
    private $pk = 'id';
    
    public function __construct($testMode = false) {
        $this->mysqlModel = MySQLModel::getInstance($testMode);
    }
    
    /**
     * 根据用户ID获取客户消息记录
     */
    public function getMessagesByUserId($userId, $limit = 20, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_CUSTOMER_MESSAGE);
        $sql = "SELECT * FROM {$tableName} WHERE user_id = :user_id ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 根据状态获取客户消息记录
     */
    public function getMessagesByStatus($status, $limit = 20, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_CUSTOMER_MESSAGE);
        $sql = "SELECT * FROM {$tableName} WHERE status = :status ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'status' => $status,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 获取未查看的消息数量
     */
    public function getUnlookedCount($userId = null) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_CUSTOMER_MESSAGE);
        $sql = "SELECT COUNT(*) as count FROM {$tableName} WHERE looked = 0";
        $params = [];
        
        if ($userId !== null) {
            $sql .= " AND user_id = :user_id";
            $params['user_id'] = $userId;
        }
        
        $result = $this->mysqlModel->queryOne($sql, $params);
        return $result['count'] ?? 0;
    }
    
    /**
     * 创建客户消息记录
     */
    public function createCustomerMessage($messageData) {
        // 使用CustomerMessageTable填充默认值
        $messageData = CustomerMessageTable::fillDefaults($messageData);
        return $this->mysqlModel->insert($this->mysqlModel->getTableName(self::TABLE_CUSTOMER_MESSAGE), $messageData);
    }
    
    /**
     * 更新客户消息记录
     */
    public function updateCustomerMessage($messageId, $messageData) {
        // 更新时间
        $messageData['updatetime'] = time();
        
        return $this->mysqlModel->update(
            $this->mysqlModel->getTableName(self::TABLE_CUSTOMER_MESSAGE),
            $messageData,
            "{$this->pk} = :message_id", ['message_id' => $messageId]
        );
    }
    
    /**
     * 根据ID获取客户消息记录
     */
    public function getMessageById($messageId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_CUSTOMER_MESSAGE);
        $sql = "SELECT * FROM {$tableName} WHERE {$this->pk} = :message_id";
        return $this->mysqlModel->queryOne($sql, ['message_id' => $messageId]);
    }
    
    /**
     * 标记消息为已查看
     */
    public function markAsLooked($messageId) {
        return $this->updateCustomerMessage($messageId, ['looked' => 1]);
    }
    
    /**
     * 更新消息状态
     */
    public function updateStatus($messageId, $status) {
        return $this->updateCustomerMessage($messageId, ['status' => $status]);
    }
    
    /**
     * 添加回答
     */
    public function addAnswer($messageId, $answer) {
        return $this->updateCustomerMessage($messageId, [
            'answer' => $answer,
            'status' => 'answer'
        ]);
    }
    
    /**
     * 获取客户消息统计
     */
    public function getMessageStats($userId = null) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_CUSTOMER_MESSAGE);
        $sql = "SELECT 
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_messages,
                    SUM(CASE WHEN status = 'answer' THEN 1 ELSE 0 END) as answered_messages,
                    SUM(CASE WHEN looked = 0 THEN 1 ELSE 0 END) as unlooked_messages
                FROM {$tableName}";
        $params = [];
        
        if ($userId !== null) {
            $sql .= " WHERE user_id = :user_id";
            $params['user_id'] = $userId;
        }
        
        return $this->mysqlModel->queryOne($sql, $params);
    }
}
?>