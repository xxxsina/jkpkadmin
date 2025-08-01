<?php
/**
 * 系统配置接口
 * 提供客服表单配置和快应用配置管理
 */

require_once __DIR__ . '/../utils/ApiUtils.php';

// 处理CORS
ApiUtils::handleCors();

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

/**
 * 获取默认配置
 */
function getDefaultConfig() {
    return [
        // 客服配置
        'customer_service' => [
            'image_upload_enabled' => true,  // 是否启用图片上传
            'video_upload_enabled' => true,  // 是否启用视频上传
            'max_image_size' => '5',        // 最大图片大小(MB)
            'max_video_size' => '50',       // 最大视频大小(MB)
            'allowed_image_types' => 'jpg,jpeg,png,gif',    // 允许的图片类型
            'allowed_video_types' => 'mp4,avi,mov'          // 允许的视频类型
        ],
        // 快应用配置
        'quick_app' => [
            'enabled'   => '0', // 是否启用快应用
            'name'      => '', // 快应用名称
            'url'       => '', // 快应用URL
            'icon'      => '' // 快应用图标
        ],
        // 更新时间
        'update_time' => strval(time())
    ];
}

try {
    ApiUtils::success('获取配置成功', getDefaultConfig());
} catch (Exception $e) {
    error_log('System Config API Error: ' . $e->getMessage());
    ApiUtils::error('服务器内部错误', 500);
}

/**
 * 使用示例:

1. 获取广告配置:
   GET http://jiankangpaika.blcwg.com/jkpk/api/system_config.php

响应格式:
 {
    "code": 200,
    "message": "获取配置成功",
    "timestamp": 1752411019,
    "datetime": "2025-07-13 20:50:19",
    "data": {
        "customer_service": {
            "image_upload_enabled": true,
            "video_upload_enabled": true,
            "max_image_size": "2",
            "max_video_size": "10",
            "allowed_image_types": "jpg,jpeg,png,gif",
            "allowed_video_types": "mp4,avi,mov"
        },
        "quick_app": {
            "enabled": "0",
            "name": "快应用",
            "url": "",
            "icon": ""
        },
        "update_time": "1752411019"
    }
}
 */
?>