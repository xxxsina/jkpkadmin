<?php

namespace app\admin\model;

use think\Model;

class ScoreCalendar extends Model
{
    // 表名
    protected $name = 'score_calendar';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];

    protected $types = [
        'check_in' => '签到',
        'add_score' => '赚取更多',
    ];

    public function getDateStampAttr($value)
    {
        if (empty($value)) {
            return '';
        }

        return date('Y-m-d', $value);
    }

    public function getTypeAttr($value)
    {
        if (empty($value)) {
            return '';
        }

        return $this->types[$value] ?? '未知';
    }

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id', [], 'LEFT');
    }
}
