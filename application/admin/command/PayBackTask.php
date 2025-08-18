<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 获取每日收益
 * 用法：
 * crontab -e
 * # 每一小时执行一次
 * 0 * * * * php /www/wwwroot/jiankangpaika.blcwg.com/think task:pay_back >> /www/wwwroot/jiankangpaika.blcwg.com/runtime/log/payback_$(date +\%Y\%m\%d).log 2>&1
 */
class PayBackTask extends Command
{
    protected function configure()
    {
        // 设置命令名称和描述
        $this->setName('task:pay_back')
            ->setDescription('payback task command');
    }

    protected function execute(Input $input, Output $output)
    {
        // 脚本任务逻辑
        $output->writeln('Task is running...');

        $time = time();
        $time = $time - 2;
        $year = date('Y', $time);
        $month = date('n', $time);
        $day = date('j', $time);

        $output->writeln('日期: ' . date('Y-m-d H:i:s', $time));

        // type = check_in
        $jl_check_in_num = db("score_calendar")
            ->where(['type' => 'check_in', 'year' => $year, 'month' => $month, 'day' => $day])
            ->sum('numb');

        // type = add_score
        $jl_add_score_num = db("score_calendar")
            ->where(['type' => 'add_score', 'year' => $year, 'month' => $month, 'day' => $day])
            ->sum('numb');

//        $output->writeln('Jump is count: ' . $jl_check_in_num);
//        $output->writeln('Jump is count: ' . $jl_add_score_num);
        $row = db("income_and_settlement")->order('id', 'desc')->find();
        if (empty($row)) {
            // 激励视频价格
            $jl_price = 0.003;
            $jl_more_price = 0.003;
        } else {
            // 激励视频价格
            $jl_price = $row['jl_price'];
            $jl_more_price = $row['jl_more_price'];
        }

        // 计算激励总价
        $jl_money = bcmul($jl_check_in_num, $jl_price, 2);
        $jl_more_money = bcmul($jl_add_score_num, $jl_more_price, 2);
        // 初步计算 当日收益
        $income = bcadd($jl_money, $jl_more_money, 2);
        // 初步计算 未结算收益
        $settlement_no = $income;

        $unique_random = md5($year . "|" . $month . "|" . $day);
        if (!empty($row) && $row['unique_random'] == $unique_random) {
            $income = bcadd($row['kp_money'], $income, 2);
            $income = bcadd($row['xx_money'], $income, 2);
            $income = bcadd($row['banner_money'], $income, 2);
            $income = bcadd($row['cp_money'], $income, 2);
            $settlement_no = bcsub($income, $row['settlement_yes'], 2);
            $update = [
                'income' => $income,
                'jl_price' => $jl_price,    // 单个激励视频价格
                'jl_num' => $jl_check_in_num, // 当日观看激励视频个数
                'jl_more_price' => $jl_more_price, // 单个激励视频价格(更多激励)
                'jl_more_num' => $jl_add_score_num, // 当日观看激励视频个数(更多激励)
                'jl_money' => $jl_money,
                'jl_more_money' => $jl_more_money,
                'settlement_no' => $settlement_no,
                'updatetime' => $time,
            ];
            db("income_and_settlement")->where(['id' => $row['id']])->update($update);
        } else {
            // insert jkpk_income_and_settlement
            $data = [
                'unique_random' => $unique_random,
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'income' => $income,
                'jl_price' => $jl_price,    // 单个激励视频价格
                'jl_num' => $jl_check_in_num, // 当日观看激励视频个数
                'jl_more_price' => $jl_more_price, // 单个激励视频价格(更多激励)
                'jl_more_num' => $jl_add_score_num, // 当日观看激励视频个数(更多激励)
                'jl_money' => $jl_money,
                'jl_more_money' => $jl_more_money,
                'settlement_no' => $settlement_no,
                'date_stamp' => strtotime(date('Y-m-d', $time)),
                'updatetime' => $time,
                'createtime' => $time,
            ];
            db("income_and_settlement")
                ->insert($data);
        }

        $output->writeln('Task completed.');
    }
}
