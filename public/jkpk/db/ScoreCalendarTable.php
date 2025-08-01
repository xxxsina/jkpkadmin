<?php
/**
 * 签到日历表字段配置
 * 管理签到日历表的表名和字段定义
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

class ScoreCalendarTable {
    
    /**
     * 表名（不带前缀）
     */
    const TABLE_NAME = 'score_calendar';
    
    /**
     * 主键字段
     */
    const PRIMARY_KEY = 'id';
    
    /**
     * 所有字段列表
     */
    const FIELDS = [
        'id',
        'user_id',
        'type',
        'numb',
        'is_complete',
        'date_stamp',
        'year',
        'month',
        'day',
        'updatetime',
        'createtime'
    ];
    
    /**
     * 可更新的字段列表（排除主键和创建时间等）
     */
    const UPDATABLE_FIELDS = [
        'user_id',
        'type',
        'numb',
        'is_complete',
        'date_stamp',
        'year',
        'month',
        'day',
        'updatetime'
    ];
    
    /**
     * 字段默认值
     */
    const FIELD_DEFAULTS = [
        'user_id' => 1,
        'type' => '',
        'numb' => 0,
        'is_complete' => 0,
        'date_stamp' => 0,
        'year' => 0,
        'month' => 0,
        'day' => 0
    ];

    /**
     * 获取表名（不带前缀）
     */
    public static function getTableName() {
        return self::TABLE_NAME;
    }
    
    /**
     * 获取主键字段名
     */
    public static function getPrimaryKey() {
        return self::PRIMARY_KEY;
    }
    
    /**
     * 获取所有字段
     */
    public static function getFields() {
        return self::FIELDS;
    }
    
    /**
     * 获取可更新字段
     */
    public static function getUpdatableFields() {
        return self::UPDATABLE_FIELDS;
    }
    
    /**
     * 获取字段默认值
     */
    public static function getFieldDefaults() {
        return self::FIELD_DEFAULTS;
    }
    
    /**
     * 为数据填充默认值
     */
    public static function fillDefaults($data) {
        $currentTime = time();
        
        // 设置时间字段默认值
        if (!isset($data['createtime'])) {
            $data['createtime'] = $currentTime;
        }
        if (!isset($data['updatetime'])) {
            $data['updatetime'] = $currentTime;
        }
        
        // 如果没有设置日期相关字段，使用当前日期
        if (!isset($data['date_stamp'])) {
            $data['date_stamp'] = strtotime(date('Y-m-d'));
        }
        if (!isset($data['year'])) {
            $data['year'] = (int)date('Y');
        }
        if (!isset($data['month'])) {
            $data['month'] = (int)date('n');
        }
        if (!isset($data['day'])) {
            $data['day'] = (int)date('j');
        }
        
        // 填充其他默认值
        foreach (self::FIELD_DEFAULTS as $field => $defaultValue) {
            if (!isset($data[$field])) {
                $data[$field] = $defaultValue;
            }
        }
        
        return $data;
    }
}