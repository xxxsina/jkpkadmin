<?php

/**
 * 用户数据访问模型类
 * 提供用户相关的数据库操作接口
 */

require_once 'MySQLModel.php';
require_once __DIR__ . '/../db/UserTable.php';

class UserModel {
    private $mysqlModel;
    
    // 表名常量
    private const TABLE_USER = UserTable::TABLE_NAME;
    
    // 主键字段
    private $pk = 'id';
    
    public function __construct($testMode = false) {
        $this->mysqlModel = MySQLModel::getInstance($testMode);
    }
    
    /**
     * 检查用户是否存在
     */
    public function userExists($field, $value) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_USER);
        $sql = "SELECT COUNT(*) as count FROM {$tableName} WHERE {$field} = :value";
        $result = $this->mysqlModel->queryOne($sql, ['value' => $value]);
        return $result['count'] > 0;
    }
    
    /**
     * 根据用户名、手机号或邮箱获取用户信息
     */
    public function getUserByIdentifier($identifier) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_USER);
        $sql = "SELECT * FROM {$tableName} WHERE username = :identifier OR phone = :identifier OR email = :identifier";
        return $this->mysqlModel->queryOne($sql, ['identifier' => $identifier]);
    }
    
    /**
     * 根据用户ID获取用户信息
     */
    public function getUserById($userId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_USER);
        $sql = "SELECT * FROM {$tableName} WHERE {$this->pk} = :user_id";
        return $this->mysqlModel->queryOne($sql, ['user_id' => $userId]);
    }
    
    /**
     * 创建新用户
     */
    public function createUser($userData) {
        return $this->mysqlModel->insert($this->mysqlModel->getTableName(self::TABLE_USER), $userData);
    }
    
    /**
     * 更新用户信息
     */
    public function updateUser($userId, $userData) {
        return $this->mysqlModel->update(
            $this->mysqlModel->getTableName(self::TABLE_USER),
            $userData,
            "{$this->pk} = :user_id", ['user_id' => $userId]
        );
    }
    

}