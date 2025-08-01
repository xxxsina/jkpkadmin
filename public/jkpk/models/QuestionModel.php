<?php
/**
 * 常见问题数据访问模型类
 * 提供常见问题相关的数据库操作接口
 */

require_once 'MySQLModel.php';
require_once __DIR__ . '/../db/QuestionTable.php';

class QuestionModel {
    private $mysqlModel;
    
    // 表名常量
    private const TABLE_QUESTION = QuestionTable::TABLE_NAME;
    
    // 主键字段
    private $pk = 'id';
    
    public function __construct($testMode = false) {
        $this->mysqlModel = MySQLModel::getInstance($testMode);
    }
    
    /**
     * 获取常见问题列表（分页）
     */
    public function getQuestions($limit = 10, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QUESTION);
        $sql = "SELECT * FROM {$tableName} WHERE `switch` = 1 ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 获取常见问题总数
     */
    public function getTotalCount() {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QUESTION);
        $sql = "SELECT COUNT(*) as count FROM {$tableName} WHERE `switch` = 1";
        $result = $this->mysqlModel->queryOne($sql);
        return $result['count'] ?? 0;
    }
    
    /**
     * 根据ID获取常见问题记录
     */
    public function getQuestionById($questionId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QUESTION);
        $sql = "SELECT * FROM {$tableName} WHERE {$this->pk} = :question_id";
        return $this->mysqlModel->queryOne($sql, ['question_id' => $questionId]);
    }
    
    /**
     * 创建常见问题记录
     */
    public function createQuestion($questionData) {
        // 使用QuestionTable填充默认值
        $questionData = QuestionTable::fillDefaults($questionData);
        return $this->mysqlModel->insert($this->mysqlModel->getTableName(self::TABLE_QUESTION), $questionData);
    }
    
    /**
     * 更新常见问题记录
     */
    public function updateQuestion($questionId, $questionData) {
        // 更新时间
        $questionData['updatetime'] = time();
        
        return $this->mysqlModel->update(
            $this->mysqlModel->getTableName(self::TABLE_QUESTION),
            $questionData,
            "{$this->pk} = :question_id", ['question_id' => $questionId]
        );
    }
    
    /**
     * 更新问题状态
     */
    public function updateQuestionStatus($questionId, $status) {
        return $this->updateQuestion($questionId, ['switch' => $status]);
    }
    
    /**
     * 获取启用的问题数量
     */
    public function getActiveQuestionCount() {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QUESTION);
        $sql = "SELECT COUNT(*) as count FROM {$tableName} WHERE `switch` = 1";
        $result = $this->mysqlModel->queryOne($sql);
        return $result['count'] ?? 0;
    }
}
?>