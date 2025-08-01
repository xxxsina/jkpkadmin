<?php

/**
 * 签到日志数据访问模型类
 * 提供签到日志相关的数据库操作接口
 */

require_once 'MySQLModel.php';
require_once __DIR__ . '/../db/QiandaoLogTable.php';

class QiandaoLogModel {
    private $mysqlModel;
    
    // 表名常量
    private const TABLE_QIANDAO_LOG = QiandaoLogTable::TABLE_NAME;
    
    // 主键字段
    private $pk = 'id';
    
    public function __construct($testMode = false) {
        $this->mysqlModel = MySQLModel::getInstance($testMode);
    }
    
    /**
     * 创建签到日志记录
     */
    public function createQiandaoLog($logData) {
        // 使用QiandaoLogTable填充默认值
        $logData = QiandaoLogTable::fillDefaults($logData);
        return $this->mysqlModel->insert($this->mysqlModel->getTableName(self::TABLE_QIANDAO_LOG), $logData);
    }
    
    /**
     * 根据ID获取签到日志信息
     */
    public function getQiandaoLogById($logId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QIANDAO_LOG);
        $sql = "SELECT * FROM {$tableName} WHERE {$this->pk} = :log_id";
        return $this->mysqlModel->queryOne($sql, ['log_id' => $logId]);
    }
    
    /**
     * 根据用户ID获取签到日志列表
     */
    public function getQiandaoLogsByUserId($userId, $limit = 50, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QIANDAO_LOG);
        $sql = "SELECT * FROM {$tableName} WHERE user_id = :user_id ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 获取用户今日签到记录
     */
    public function getTodayQiandaoLogByUserId($userId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QIANDAO_LOG);
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $todayEnd = strtotime(date('Y-m-d 23:59:59'));
        
        $sql = "SELECT * FROM {$tableName} WHERE user_id = :user_id AND createtime >= :today_start AND createtime <= :today_end ORDER BY createtime DESC";
        return $this->mysqlModel->query($sql, [
            'user_id' => $userId,
            'today_start' => $todayStart,
            'today_end' => $todayEnd
        ]);
    }
    
    /**
     * 检查用户今日是否已签到
     */
    public function hasUserCheckedInToday($userId) {
        $todayLogs = $this->getTodayQiandaoLogByUserId($userId);
        return !empty($todayLogs);
    }
    
    /**
     * 获取用户今日签到次数
     */
    public function getTodayCheckinCount($userId) {
        $todayLogs = $this->getTodayQiandaoLogByUserId($userId);
        return count($todayLogs);
    }
    
    /**
     * 根据时间范围获取签到日志
     */
    public function getQiandaoLogsByDateRange($userId, $startTime, $endTime, $limit = 100, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QIANDAO_LOG);
        $sql = "SELECT * FROM {$tableName} WHERE user_id = :user_id AND createtime >= :start_time AND createtime <= :end_time ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'user_id' => $userId,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 获取用户本月签到记录
     */
    public function getMonthlyQiandaoLogs($userId, $year = null, $month = null) {
        if ($year === null) {
            $year = date('Y');
        }
        if ($month === null) {
            $month = date('n');
        }
        
        $startTime = strtotime("{$year}-{$month}-01 00:00:00");
        $endTime = strtotime(date('Y-m-t 23:59:59', $startTime));
        
        return $this->getQiandaoLogsByDateRange($userId, $startTime, $endTime, 100, 0);
    }
    
    /**
     * 更新签到日志信息
     */
    public function updateQiandaoLog($logId, $logData) {
        // 自动更新updatetime
        $logData['updatetime'] = time();
        
        return $this->mysqlModel->update(
            $this->mysqlModel->getTableName(self::TABLE_QIANDAO_LOG),
            $logData,
            "{$this->pk} = :log_id", ['log_id' => $logId]
        );
    }
    
    /**
     * 删除签到日志记录
     */
    public function deleteQiandaoLog($logId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QIANDAO_LOG);
        $sql = "DELETE FROM {$tableName} WHERE {$this->pk} = :log_id";
        return $this->mysqlModel->execute($sql, ['log_id' => $logId]);
    }
    
    /**
     * 统计签到日志数量
     */
    public function countQiandaoLogs($conditions = []) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QIANDAO_LOG);
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
    
    /**
     * 获取用户连续签到天数
     */
    public function getUserConsecutiveCheckinDays($userId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_QIANDAO_LOG);
        
        // 获取用户最近30天的签到记录，按日期分组
        $sql = "SELECT DATE(FROM_UNIXTIME(createtime)) as checkin_date 
                FROM {$tableName} 
                WHERE user_id = :user_id 
                AND createtime >= :thirty_days_ago 
                GROUP BY DATE(FROM_UNIXTIME(createtime)) 
                ORDER BY checkin_date DESC";
        
        $thirtyDaysAgo = strtotime('-30 days');
        $records = $this->mysqlModel->query($sql, [
            'user_id' => $userId,
            'thirty_days_ago' => $thirtyDaysAgo
        ]);
        
        if (empty($records)) {
            return 0;
        }
        
        // 计算连续签到天数
        $consecutiveDays = 0;
        $today = date('Y-m-d');
        $currentDate = $today;
        
        foreach ($records as $record) {
            if ($record['checkin_date'] === $currentDate) {
                $consecutiveDays++;
                $currentDate = date('Y-m-d', strtotime($currentDate . ' -1 day'));
            } else {
                break;
            }
        }
        
        return $consecutiveDays;
    }
}