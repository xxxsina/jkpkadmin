<?php
/**
 * 用户积分变动数据访问模型类
 * 提供用户积分变动相关的数据库操作接口
 */

require_once 'MySQLModel.php';
require_once __DIR__ . '/../db/UserScoreLogTable.php';

class UserScoreLogModel {
    private $mysqlModel;
    
    // 表名常量
    private const TABLE_USER_SCORE_LOG = UserScoreLogTable::TABLE_NAME;
    
    // 主键字段
    private $pk = 'id';
    
    public function __construct($testMode = false) {
        $this->mysqlModel = MySQLModel::getInstance($testMode);
    }
    
    /**
     * 根据用户ID获取积分变动记录
     */
    public function getScoreLogsByUserId($userId, $limit = 20, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_USER_SCORE_LOG);
        $sql = "SELECT * FROM {$tableName} WHERE user_id = :user_id ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 根据用户ID和月份获取积分变动记录
     */
    public function getScoreLogsByUserIdAndMonth($userId, $month, $year = null) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_USER_SCORE_LOG);
        if ($year === null) {
            $year = date('Y');
        }
        $sql = "SELECT * FROM {$tableName} WHERE user_id = :user_id AND month = :month AND YEAR(FROM_UNIXTIME(createtime)) = :year ORDER BY createtime DESC";
        return $this->mysqlModel->query($sql, [
            'user_id' => $userId,
            'month' => $month,
            'year' => $year
        ]);
    }
    
    /**
     * 获取用户今日签到次数
     */
    public function getTodayCheckinCount($userId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_USER_SCORE_LOG);
        $today = date('j');
        $month = date('n');
        $year = date('Y');
        
        $sql = "SELECT COUNT(*) as count FROM {$tableName} WHERE user_id = :user_id AND day = :day AND month = :month AND YEAR(FROM_UNIXTIME(createtime)) = :year AND memo LIKE '%签到%'";
        $result = $this->mysqlModel->queryOne($sql, [
            'user_id' => $userId,
            'day' => $today,
            'month' => $month,
            'year' => $year
        ]);
        return $result['count'] ?? 0;
    }
    
    /**
     * 创建积分变动记录
     */
    public function createScoreLog($scoreLogData) {
        // 使用UserScoreLogTable填充默认值
        $scoreLogData = UserScoreLogTable::fillDefaults($scoreLogData);
        return $this->mysqlModel->insert($this->mysqlModel->getTableName(self::TABLE_USER_SCORE_LOG), $scoreLogData);
    }
    
    /**
     * 更新积分变动记录
     */
    public function updateScoreLog($scoreLogId, $scoreLogData) {
        return $this->mysqlModel->update(
            $this->mysqlModel->getTableName(self::TABLE_USER_SCORE_LOG),
            $scoreLogData,
            "{$this->pk} = :score_log_id", ['score_log_id' => $scoreLogId]
        );
    }
    
    /**
     * 根据ID获取积分变动记录
     */
    public function getScoreLogById($scoreLogId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_USER_SCORE_LOG);
        $sql = "SELECT * FROM {$tableName} WHERE {$this->pk} = :score_log_id";
        return $this->mysqlModel->queryOne($sql, ['score_log_id' => $scoreLogId]);
    }
    
    /**
     * 获取用户总积分变动统计
     */
    public function getUserScoreStats($userId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_USER_SCORE_LOG);
        $sql = "SELECT 
                    SUM(CASE WHEN score > 0 THEN score ELSE 0 END) as total_earned,
                    SUM(CASE WHEN score < 0 THEN ABS(score) ELSE 0 END) as total_spent,
                    COUNT(*) as total_records
                FROM {$tableName} WHERE user_id = :user_id";
        return $this->mysqlModel->queryOne($sql, ['user_id' => $userId]);
    }
}
?>