<?php

namespace app\admin\model;

use think\Model;


class Payback extends Model
{

    

    

    // 表名
    protected $name = 'income_and_settlement';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];

    public function getDateStampAttr($value)
    {
        return date("Y-m-d", $value);
    }
}
