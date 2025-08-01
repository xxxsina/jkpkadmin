<?php
/**
 * 积分日历数据访问模型类
 * 提供积分日历相关的数据库操作接口
 */

require_once 'MySQLModel.php';
require_once __DIR__ . '/../db/ScoreCalendarTable.php';

class ScoreCalendarModel {
    private $mysqlModel;
    
    // 表名常量
    private const TABLE_SCORE_CALENDAR = ScoreCalendarTable::TABLE_NAME;
    
    // 主键字段
    private $pk = 'id';
    
    public function __construct($testMode = false) {
        $this->mysqlModel = MySQLModel::getInstance($testMode);
    }
    
    /**
     * 创建积分日历记录
     */
    public function createScoreCalendar($calendarData) {
        // 使用ScoreCalendarTable填充默认值
        $calendarData = ScoreCalendarTable::fillDefaults($calendarData);
        return $this->mysqlModel->insert($this->mysqlModel->getTableName(self::TABLE_SCORE_CALENDAR), $calendarData);
    }
    
    /**
     * 根据ID获取积分日历信息
     */
    public function getScoreCalendarById($calendarId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_SCORE_CALENDAR);
        $sql = "SELECT * FROM {$tableName} WHERE {$this->pk} = :calendar_id";
        return $this->mysqlModel->queryOne($sql, ['calendar_id' => $calendarId]);
    }
    
    /**
     * 根据用户ID获取积分日历列表
     */
    public function getScoreCalendarsByUserId($userId, $limit = 50, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_SCORE_CALENDAR);
        $sql = "SELECT * FROM {$tableName} WHERE user_id = :user_id ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 根据用户ID和日期获取积分日历记录
     */
    public function getScoreCalendarByUserAndDate($userId, $year, $month, $day) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_SCORE_CALENDAR);
        $sql = "SELECT * FROM {$tableName} WHERE user_id = :user_id AND year = :year AND month = :month AND day = :day";
        return $this->mysqlModel->query($sql, [
            'user_id' => $userId,
            'year' => $year,
            'month' => $month,
            'day' => $day
        ]);
    }
    
    /**
     * 根据用户ID和类型获取积分日历记录
     */
    public function getScoreCalendarByUserAndType($userId, $type, $limit = 50, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_SCORE_CALENDAR);
        $sql = "SELECT * FROM {$tableName} WHERE user_id = :user_id AND type = :type ORDER BY createtime DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'user_id' => $userId,
            'type' => $type,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 获取用户今日积分日历记录
     */
    public function getTodayScoreCalendarByUserId($userId) {
        $today = date('Y-m-d');
        $year = (int)date('Y');
        $month = (int)date('n');
        $day = (int)date('j');
        
        return $this->getScoreCalendarByUserAndDate($userId, $year, $month, $day);
    }
    
    /**
     * 检查用户今日是否已有指定类型的记录
     */
    public function hasUserTodayRecord($userId, $type) {
        $year = (int)date('Y');
        $month = (int)date('n');
        $day = (int)date('j');
        
        $tableName = $this->mysqlModel->getTableName(self::TABLE_SCORE_CALENDAR);
        $sql = "SELECT COUNT(*) as count FROM {$tableName} WHERE user_id = :user_id AND type = :type AND year = :year AND month = :month AND day = :day";
        $result = $this->mysqlModel->queryOne($sql, [
            'user_id' => $userId,
            'type' => $type,
            'year' => $year,
            'month' => $month,
            'day' => $day
        ]);
        return $result['count'] > 0;
    }
    
    /**
     * 获取用户本月积分日历记录
     */
    public function getMonthlyScoreCalendar($userId, $year = null, $month = null) {
        if ($year === null) {
            $year = (int)date('Y');
        }
        if ($month === null) {
            $month = (int)date('n');
        }
        
        $tableName = $this->mysqlModel->getTableName(self::TABLE_SCORE_CALENDAR);
        $sql = "SELECT * FROM {$tableName} WHERE user_id = :user_id AND year = :year AND month = :month ORDER BY day ASC";
        return $this->mysqlModel->query($sql, [
            'user_id' => $userId,
            'year' => $year,
            'month' => $month
        ]);
    }
    
    /**
     * 根据时间戳范围获取积分日历记录
     */
    public function getScoreCalendarsByDateRange($userId, $startStamp, $endStamp, $limit = 100, $offset = 0) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_SCORE_CALENDAR);
        $sql = "SELECT * FROM {$tableName} WHERE user_id = :user_id AND date_stamp >= :start_stamp AND date_stamp <= :end_stamp ORDER BY date_stamp DESC LIMIT :limit OFFSET :offset";
        return $this->mysqlModel->query($sql, [
            'user_id' => $userId,
            'start_stamp' => $startStamp,
            'end_stamp' => $endStamp,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * 更新积分日历记录
     */
    public function updateScoreCalendar($calendarId, $calendarData) {
        // 自动更新updatetime
        $calendarData['updatetime'] = time();
        
        return $this->mysqlModel->update(
            $this->mysqlModel->getTableName(self::TABLE_SCORE_CALENDAR),
            $calendarData,
            "{$this->pk} = :calendar_id", ['calendar_id' => $calendarId]
        );
    }
    
    /**
     * 增加用户指定日期的积分次数
     */
    public function incrementUserDateNumb($userId, $type, $year, $month, $day, $newNumb = 1) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_SCORE_CALENDAR);
        
        // 先查找是否存在记录
        $existing = $this->getScoreCalendarByUserAndDate($userId, $year, $month, $day);
        $typeRecord = null;
        foreach ($existing as $record) {
            if ($record['type'] === $type) {
                $typeRecord = $record;
                break;
            }
        }

        // 检查是否完成
        $config = require __DIR__ . '/../config/config.php';
        $maxCheckinPerDay = $config['checkin_config']['max_per_day'] ?? 10;
        $maxScoreAgainMore = $config['checkin_config']['max_score_again_more'] ?? 10;
        if ($type == 'check_in') {
            $maxCount = $maxCheckinPerDay;
        } else if ($type == 'add_score') {
            $maxCount = $maxScoreAgainMore;
        } else {
            $maxCount = 0;
        }
        $is_complete = $newNumb == $maxCount ? 1 : 0;

        if ($typeRecord) {
            // 更新现有记录
            return $this->updateScoreCalendar($typeRecord['id'], ['numb' => $newNumb, 'is_complete' => $is_complete]);
        } else {
            // 创建新记录
            $calendarData = [
                'user_id' => $userId,
                'type' => $type,
                'numb' => $newNumb,
                'is_complete' => $is_complete,
                'date_stamp' => strtotime("{$year}-{$month}-{$day}"),
                'year' => $year,
                'month' => $month,
                'day' => $day
            ];
            return $this->createScoreCalendar($calendarData);
        }
    }
    
    /**
     * 删除积分日历记录
     */
    public function deleteScoreCalendar($calendarId) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_SCORE_CALENDAR);
        $sql = "DELETE FROM {$tableName} WHERE {$this->pk} = :calendar_id";
        return $this->mysqlModel->execute($sql, ['calendar_id' => $calendarId]);
    }
    
    /**
     * 统计积分日历记录数量
     */
    public function countScoreCalendars($conditions = []) {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_SCORE_CALENDAR);
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
     * 获取用户连续积分天数
     */
    public function getUserConsecutiveScoreDays($userId, $type = 'Score_in') {
        $tableName = $this->mysqlModel->getTableName(self::TABLE_SCORE_CALENDAR);
        
        // 获取用户最近30天的积分记录，按日期分组
        $sql = "SELECT DISTINCT year, month, day, date_stamp 
                FROM {$tableName} 
                WHERE user_id = :user_id AND type = :type 
                AND date_stamp >= :thirty_days_ago 
                ORDER BY date_stamp DESC";
        
        $thirtyDaysAgo = strtotime('-30 days');
        $records = $this->mysqlModel->query($sql, [
            'user_id' => $userId,
            'type' => $type,
            'thirty_days_ago' => $thirtyDaysAgo
        ]);
        
        if (empty($records)) {
            return 0;
        }
        
        // 计算连续积分天数
        $consecutiveDays = 0;
        $today = strtotime(date('Y-m-d'));
        $currentStamp = $today;
        
        foreach ($records as $record) {
            if ($record['date_stamp'] == $currentStamp) {
                $consecutiveDays++;
                $currentStamp = strtotime('-1 day', $currentStamp);
            } else {
                break;
            }
        }
        
        return $consecutiveDays;
    }
}