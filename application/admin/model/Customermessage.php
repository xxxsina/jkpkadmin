<?php

namespace app\admin\model;

use think\Cache;
use think\Config;
use think\Model;


class Customermessage extends Model
{
    // 表名
    protected $name = 'customer_message';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];

    protected static $redis = null;
    protected static $redisKeyModel = null;

//    protected static function init() {
//        self::$redis = Cache::store('redis')->handler();
//    }

    public function __construct($data = [])
    {
        parent::__construct($data);
//        $this->redisKeyModel = new RedisNameConfigModel();
        self::$redisKeyModel = new RedisNameConfigModel();
    }

    protected static function init()
    {
        // 更新后事件
        self::event('after_update', function ($model) {
            $redis = Cache::store('redis')->handler();
            // 更新后的处理
            $redis->hMSet(self::$redisKeyModel->getCustomerMessagesKey($model->user_id, $model->id), $model->toArray());
        });

        // 删除后事件
        self::event('after_delete', function ($model) {
            $redis = Cache::store('redis')->handler();
            // 删除后的处理
            $redis->del(self::$redisKeyModel->getCustomerMessagesKey($model->user_id, $model->id));
            $redis->zRem(self::$redisKeyModel->getCustomerMessagesListKey($model->user_id), $model->id);
        });
    }

    // 在Customermessage模型中
    public function getImageAttr($value)
    {
        if (empty($value)) {
            return '';
        }

        return Config::get("web_host") . '/' . $value;
    }

    public function getVideoAttr($value)
    {
        if (empty($value)) {
            return '';
        }

        return Config::get("web_host") . '/' . $value;
    }
}