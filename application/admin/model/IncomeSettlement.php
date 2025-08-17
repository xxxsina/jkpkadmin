<?php

namespace app\admin\model;

use think\Model;


class IncomeSettlement extends Model
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

    // 定义jl_price字段的修改器
    public function setJlPriceAttr($value, $data)
    {
        $oldValue = $this->getAttr('jl_price');

        // 执行修改前的逻辑
        if ($oldValue != $value) {
            $jl_money = bcmul($value, $data['jl_num'], 2);
            $this->setAttr('jl_money', $jl_money);
        }

        return $value;
    }

    public function setJlMorePriceAttr($value, $data)
    {
        $oldValue = $this->getAttr('jl_more_price');

        // 执行修改前的逻辑
        if ($oldValue != $value) {
            $jl_more_money = bcmul($value, $data['jl_more_num'], 2);
            $this->setAttr('jl_more_money', $jl_more_money);
        }

        return $value;
    }

    public function setSettlementYesAttr($value, $data)
    {
        $oldValue = $this->getAttr('settlement_yes');

        // 计算未结算金额
        if ($oldValue != $value) {
            $settlement_no = bcsub($data['settlement_no'], $value, 2);
            $this->setAttr('settlement_no', $settlement_no);
        }

        return $value;
    }

    protected static function init()
    {
        self::beforeWrite(function ($model) {
            $sum = 0;
            $numbers = [
                $model->kp_money,
                $model->xx_money,
                $model->banner_money,
                $model->cp_money
            ];

            foreach ($numbers as $number) {
                $sum = bcadd($sum, (string)$number, 2);
            }

            $jl_money = bcmul($model->jl_price, $model->jl_num, 2);
            $jl_more_money = bcmul($model->jl_more_price, $model->jl_more_num, 2);

            $sum = bcadd($sum, $jl_money, 2);
            $sum = bcadd($sum, $jl_more_money, 2);

            // 直接设置属性
            $model->income = $sum;
            $settlement_no = bcsub($sum, $model->settlement_yes, 2);
            $model->settlement_no = $settlement_no > 0 ? $settlement_no : 0;
        });
    }
}
