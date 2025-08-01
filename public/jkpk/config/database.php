<?php
/**
 * 数据库配置文件
 * 包含MySQL和Redis的连接配置
 */

return [
    // MySQL数据库配置
    'mysql' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'dbname' => 'jiankangpaika',
        'username' => 'jiankangpaika',
        'password' => 'iYBh37c65Zp3C7WH',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ],
        'table_prefix' => 'jkpk_'
    ],
    
    // Redis配置
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => "",
        'database' => 3,
        'timeout' => 5.0,
        'prefix' => 'jkpk:',
        'options' => [
             Redis::OPT_SERIALIZER => Redis::SERIALIZER_JSON,
             Redis::OPT_PREFIX => 'jkpk:'
            // 注释掉 Redis 常量以避免在未安装 Redis 扩展时出错
        ]
    ],
    
    // RabbitMQ配置
    'rabbitmq' => [
        'host' => '127.0.0.1',
        'port' => 5672,
        'username' => 'guest',
        'password' => 'guest',
        'vhost' => '/'
    ],
    
    // 缓存配置
    'cache' => [
        'default_ttl' => -1, // 默认缓存时间1小时
        'user_session_ttl' => 86400 * 365, // 用户会话1年
        'user_info_ttl' => -1, // 用户信息缓存1小时
        'verification_code_ttl' => 300 // 验证码5分钟
    ]
];