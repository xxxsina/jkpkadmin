<?php
/**
 * 客户消息表字段配置
 * 管理客户消息表的表名和字段定义
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

class CustomerMessageTable {
    
    /**
     * 表名（不带前缀）
     */
    const TABLE_NAME = 'customer_message';
    
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
        'status',
        'looked',
        'realname',
        'mobile',
        'problem',
        'answer',
        'image',
        'video',
        'is_overcome',
        'answer_image',
        'answer_video',
        'updatetime',
        'createtime'
    ];
    
    /**
     * 可更新的字段列表（排除主键和创建时间等）
     */
    const UPDATABLE_FIELDS = [
        'user_id',
        'status',
        'looked',
        'realname',
        'mobile',
        'problem',
        'answer',
        'image',
        'video',
        'is_overcome',
        'answer_image',
        'answer_video',
        'updatetime'
    ];
    
    /**
     * 字段默认值
     */
    const FIELD_DEFAULTS = [
        'user_id' => 1,
        'status' => 'new',
        'looked' => 0,
        'realname' => '',
        'mobile' => '',
        'problem' => '',
        'answer' => '',
        'image' => '',
        'video' => '',
        'is_overcome' => 0,
        'answer_image' => '',
        'answer_video' => '',
        'updatetime' => null,
        'createtime' => null
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
        
        // 填充其他默认值
        foreach (self::FIELD_DEFAULTS as $field => $defaultValue) {
            if (!isset($data[$field])) {
                $data[$field] = $defaultValue;
            }
        }
        
        return $data;
    }
}
?>