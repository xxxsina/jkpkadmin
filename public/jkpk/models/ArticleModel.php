<?php
/**
 * 文章数据访问模型类
 * 提供文章相关的数据库操作接口
 */

require_once 'MySQLModel.php';
require_once __DIR__ . '/../db/ArticleTable.php';

class ArticleModel {
    private $mysqlModel;
    
    // 表名常量
    private const TABLE_ARTICLE = ArticleTable::TABLE_NAME;
    
    // 主键字段
    private $pk = 'id';
    
    public function __construct($testMode = false) {
        $this->mysqlModel = MySQLModel::getInstance($testMode);
    }
    
    /**
     * 根据类型获取文章列表
     */
    public function getArticlesByType($type, $limit = 20, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_ARTICLE);
        $sql = "SELECT * FROM {$tableName} WHERE type = :type AND status = 1 ORDER BY is_sort ASC, createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'type' => $type,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 根据状态获取文章列表
     */
    public function getArticlesByStatus($status, $limit = 20, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_ARTICLE);
        $sql = "SELECT * FROM {$tableName} WHERE status = :status ORDER BY is_sort ASC, createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'status' => $status,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 获取所有文章列表（分页）
     */
    public function getAllArticles($limit = 20, $offset = 0, $onlyPublished = true) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_ARTICLE);
        $sql = "SELECT * FROM {$tableName}";
        $params = [];
        
        if ($onlyPublished) {
            $sql .= " WHERE status = 1";
        }
        
        $sql .= " ORDER BY is_sort ASC, createtime DESC LIMIT :limit OFFSET :offset";
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        
        return $this->mysqlModel->query($sql, $params);
    }
    
    /**
     * 根据管理员ID获取文章列表
     */
    public function getArticlesByAdminId($adminId, $limit = 20, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_ARTICLE);
        $sql = "SELECT * FROM {$tableName} WHERE admin_id = :admin_id ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'admin_id' => $adminId,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 创建文章记录
     */
    public function createArticle($articleData) {
        // 使用ArticleTable填充默认值
        $articleData = ArticleTable::fillDefaults($articleData);
        return $this->mysqlModel->insert($this->mysqlModel->getTableName(self::TABLE_ARTICLE), $articleData);
    }
    
    /**
     * 更新文章记录
     */
    public function updateArticle($articleId, $articleData) {
        // 更新时间
        $articleData['updatetime'] = time();
        
        return $this->mysqlModel->update(
            $this->mysqlModel->getTableName(self::TABLE_ARTICLE),
            $articleData,
            "{$this->pk} = :article_id", ['article_id' => $articleId]
        );
    }
    
    /**
     * 根据ID获取文章记录
     */
    public function getArticleById($articleId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_ARTICLE);
        $sql = "SELECT * FROM {$tableName} WHERE {$this->pk} = :article_id";
        return $this->mysqlModel->queryOne($sql, ['article_id' => $articleId]);
    }
    
    /**
     * 删除文章记录
     */
    public function deleteArticle($articleId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_ARTICLE);
        $sql = "DELETE FROM {$tableName} WHERE {$this->pk} = :article_id";
        return $this->mysqlModel->execute($sql, ['article_id' => $articleId]);
    }
    
    /**
     * 更新文章状态
     */
    public function updateStatus($articleId, $status) {
        return $this->updateArticle($articleId, ['status' => $status]);
    }
    
    /**
     * 更新文章排序
     */
    public function updateSort($articleId, $sort) {
        return $this->updateArticle($articleId, ['is_sort' => $sort]);
    }
    
    /**
     * 搜索文章（根据标题）
     */
    public function searchArticles($keyword, $limit = 20, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_ARTICLE);
        $sql = "SELECT * FROM {$tableName} WHERE title LIKE :keyword AND status = 1 ORDER BY is_sort ASC, createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'keyword' => '%' . $keyword . '%',
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 获取文章统计信息
     */
    public function getArticleStats($adminId = null) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_ARTICLE);
        $sql = "SELECT 
                    COUNT(*) as total_articles,
                    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as published_articles,
                    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as draft_articles
                FROM {$tableName}";
        $params = [];
        
        if ($adminId !== null) {
            $sql .= " WHERE admin_id = :admin_id";
            $params['admin_id'] = $adminId;
        }
        
        return $this->mysqlModel->queryOne($sql, $params);
    }
    
    /**
     * 获取文章类型统计
     */
    public function getTypeStats() {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_ARTICLE);
        $sql = "SELECT type, COUNT(*) as count FROM {$tableName} WHERE status = 1 GROUP BY type ORDER BY count DESC";
        return $this->mysqlModel->query($sql);
    }
}
?>