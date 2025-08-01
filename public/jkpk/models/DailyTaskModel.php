<?php
/**
 * 每日任务数据访问模型类
 * 提供每日任务相关的数据库操作接口
 */

require_once 'MySQLModel.php';
require_once __DIR__ . '/../db/DailyTaskTable.php';

class DailyTaskModel {
    private $mysqlModel;
    
    // 表名常量
    private const TABLE_DAILY_TASK = DailyTaskTable::TABLE_NAME;
    
    // 主键字段
    private $pk = 'id';
    
    public function __construct($testMode = false) {
        $this->mysqlModel = MySQLModel::getInstance($testMode);
    }
    
    /**
     * 获取每日任务列表（分页）
     */
    public function getDailyTasks($limit = 10, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_DAILY_TASK);
        $sql = "SELECT * FROM {$tableName} WHERE `switch` = 1 ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 获取每日任务总数
     */
    public function getTotalCount() {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_DAILY_TASK);
        $sql = "SELECT COUNT(*) as count FROM {$tableName} WHERE `switch` = 1";
        $result = $this->mysqlModel->queryOne($sql);
        return $result['count'] ?? 0;
    }
    
    /**
     * 根据ID获取每日任务记录
     */
    public function getTaskById($taskId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_DAILY_TASK);
        $sql = "SELECT * FROM {$tableName} WHERE {$this->pk} = :task_id";
        return $this->mysqlModel->queryOne($sql, ['task_id' => $taskId]);
    }
    
    /**
     * 创建每日任务记录
     */
    public function createDailyTask($taskData) {
        // 使用DailyTaskTable填充默认值
        $taskData = DailyTaskTable::fillDefaults($taskData);
        return $this->mysqlModel->insert($this->mysqlModel->getTableName(self::TABLE_DAILY_TASK), $taskData);
    }
    
    /**
     * 更新每日任务记录
     */
    public function updateDailyTask($taskId, $taskData) {
        // 更新时间
        $taskData['updatetime'] = time();
        
        return $this->mysqlModel->update(
            $this->mysqlModel->getTableName(self::TABLE_DAILY_TASK),
            $taskData,
            "{$this->pk} = :task_id", ['task_id' => $taskId]
        );
    }
    
    /**
     * 更新任务状态
     */
    public function updateTaskStatus($taskId, $status) {
        return $this->updateDailyTask($taskId, ['switch' => $status]);
    }
    
    /**
     * 获取启用的任务数量
     */
    public function getActiveTaskCount() {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_DAILY_TASK);
        $sql = "SELECT COUNT(*) as count FROM {$tableName} WHERE `switch` = 1";
        $result = $this->mysqlModel->queryOne($sql);
        return $result['count'] ?? 0;
    }
}
?>