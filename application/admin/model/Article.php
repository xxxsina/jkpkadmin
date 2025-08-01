<?php

namespace app\admin\model;

use think\Cache;
use think\Model;


class Article extends Model
{

    

    

    // 表名
    protected $name = 'article';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];

    protected static $redisKeyModel = null;

    public function __construct($data = [])
    {
        parent::__construct($data);
        self::$redisKeyModel = new RedisNameConfigModel();
    }

    protected static function init()
    {
        // 插入后事件 - 将数据存入Redis
        self::event('after_insert', function ($model) {
            $redis = Cache::store('redis')->handler();

            // 将模型数据转为数组存入Hash
            $redis->hMSet(self::$redisKeyModel->getArticleById($model->id), $model->toArray());

            // 如果需要也可以存入有序集合
            $redis->zAdd(self::$redisKeyModel->getArticleList(), $model->is_sort, $model->id);
        });

        // 更新后事件
        self::event('after_update', function ($model) {
            $redis = Cache::store('redis')->handler();
            // 更新后的处理
            $redis->hMSet(self::$redisKeyModel->getArticleById($model->id), $model->toArray());

            if ($model->status == 0) {
                $redis->zRem(self::$redisKeyModel->getArticleList(), $model->id);
            } else {
                $redis->zAdd(self::$redisKeyModel->getArticleList(), $model->is_sort, $model->id);
            }
        });

        // 删除后事件
        self::event('after_delete', function ($model) {
            $redis = Cache::store('redis')->handler();
            // 删除后的处理
            $redis->del(self::$redisKeyModel->getArticleById($model->id));
            $redis->zRem(self::$redisKeyModel->getArticleList(), $model->id);
        });
    }
    







}
