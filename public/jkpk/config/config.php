<?php
return [
    // avatar
    'HTTP_HOST' => 'http://jiankangpaika.blcwg.com',   // http://jiankangpaika.blcwg.com
    'HTTP_HOST_ADMIN' => 'http://jiankangpaika.blcwg.com', // http://jiankangpaika.blcwg.com',

    // 一号通短信配置
    'sms_config' => [
        'app_id' => 're84UhkRCKZ8RtoEZMBe',
        'app_secret' => 'esUG6YQzkaIAp8iRmRkvydKaFphRYdjSmnsD',
        'api_host' => 'http://sms.crmeb.net/api',
        'temp_id' => '1019811997',  // 默认验证码模板ID，需要根据实际情况修改
        // limit
        'time_limit' => 1000, // 发送时间间隔
        'ip_limit' => 100,  // ip发送限制个数
    ],
    
    // 每日签到页面配置
    'checkin_config' => [
        // 签到
        'max_per_day' => 10,          // 每日最大签到次数
        'score_per_checkin' => 10,    // 单次签到获得积分
        // 赚取更多积分
        'max_score_again_more' => 10, // 每日最大获奖次数
        'score_again_more'  => 10,    // 单次获奖的分数
    ],
];
?>