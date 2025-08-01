<?php
/**
 * 用户服务类
 * 封装用户相关的业务逻辑，包括注册、登录、用户信息管理等
 */

require_once __DIR__ . '/QueueService.php';
require_once __DIR__ . '/../models/RedisModel.php';

class CalendarService {
    private static $instance = null;
    private $queueService;
    private $redisModel;
    private $rname = "user:checkin:%s:calendar";
    private $field;
    private $keyDay;
    public function __construct() {
        $this->queueService = QueueService::getInstance();
        $this->redisModel = RedisModel::getInstance();
        $this->field = date("Ym");
        $this->keyDay = date("j");
    }

    /**
     * 获取单例实例
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param $field // date("Ym")
     * @return null
     * @author LEE
     * @Date 2025-06-26 13:10
     */
    public function setField($field)
    {
        $this->field = $field;
        return self::$instance;
    }

    /**
     * @param $keyDay // date("j") 1-31
     * @return null
     * @author LEE
     * @Date 2025-06-26 13:11
     */
    public function setKeyDay($keyDay)
    {
        $this->keyDay = $keyDay;
        return self::$instance;
    }

    public function setRname($userId)
    {
        $this->rname = sprintf($this->rname, $userId);
        return self::$instance;
    }

    public function setRedisCalendar($userId, $count)
    {
        // 获取配置
        $config = require __DIR__ . '/../config/config.php';
        $max_per_day = $config['checkin_config']['max_per_day'];
        
        // 判断是否完成
        $is_complete = ($count >= $max_per_day) ? 1 : 0;
        
        // 获取当月数据
        $rname = sprintf($this->rname, $userId);
        $calendarField = $this->getRedisCalendarField($rname);
        // 组合当日数据
        $calendarField[$this->keyDay] = ['count' => $count, 'is_complete' => $is_complete];
        ksort($calendarField);
        $calendar = [$this->field => $calendarField];
        $this->redisModel->hMSet($rname, $calendar);
    }

    public function getRedisCalendarField($rname = null, $field = null)
    {
        $field = !empty($field) ? $field : $this->field;
        $this->rname = !empty($rname) ? $rname : $this->rname;
        $calendarField = $this->redisModel->hGet($this->rname, $field);
        if (empty($calendarField)) return [];
        return $calendarField;
    }
}