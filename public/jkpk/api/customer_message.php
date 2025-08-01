<?php
/**
 * 客服表单接口
 * 处理客服表单提交和消息列表获取
 * 数据存储在Redis中，使用hMSet方式
 */

require_once __DIR__ . '/../utils/ApiUtils.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../services/UploadService.php';
require_once __DIR__ . '/../services/QueueService.php';
require_once __DIR__ . '/../services/UserLogService.php';
require_once __DIR__ . '/../models/RedisModel.php';

// 处理CORS
ApiUtils::handleCors();

// 获取请求方法和路径
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';

try {
    handleSubmitForm();
} catch (Exception $e) {
    error_log('Customer Service API Error: ' . $e->getMessage());
    ApiUtils::error('服务器内部错误', 500);
}

/**
 * 处理表单提交
 */
function handleSubmitForm() {
    $redis = RedisModel::getInstance();

    // 获取请求参数
    $params = ApiUtils::getRequestParams(['user_id', 'realname', 'mobile', 'problem']);
    // 从header中获取token
    $token = ApiUtils::getTokenFromHeader();
    // 创建用户服务实例
    $userService = new UserService();
    $userId = $params['user_id'];
    // 验证用户令牌（简单验证）
    if (!$userService->validateUserToken($userId, $token)) {
        ApiUtils::unauthorized('登录已过期，请退出后重新登陆');
    }
    
    // 验证手机号格式
    if (!preg_match('/^1[3-9]\d{9}$/', $params['mobile'])) {
        ApiUtils::error('手机号格式不正确', 400);
        return;
    }

    // 生成消息ID
    $messageId = $redis->incr('customer_message:next_id');
    // 创建上传服务实例
    $uploadService = new UploadService();
    $imageResult = $uploadService->handleImageUpload($userId);
    $videoResult = $uploadService->handleVideoUpload($userId);

    // 准备数据
    $messageData = [
        'id' => $messageId,
        'user_id' => $userId,
        'status' => 'new',
        'looked' => '0',
        'realname' => trim($params['realname']),
        'mobile' => trim($params['mobile']),
        'problem' => trim($params['problem']),
        'answer' => '',
        'is_overcome' => 0,
        'image' => $imageResult['success'] === true ? $imageResult['image'] : '',
        'video' => $videoResult['success'] === true ? $videoResult['video'] : '',
        'createtime' => time(),
        'updatetime' => time()
    ];
    
    // 存储到Redis
    $userKey = "customer_messages:user:{$userId}";
    $messageKey = "customer_messages:userId:{$userId}:msgId:{$messageId}";
    
    try {
        // 使用Redis事务确保数据一致性
        $redis->multi();
        
        // 存储用户提问的消息详情
        $redis->hMSet($messageKey, $messageData);
        
        // 添加到用户消息列表（使用有序集合，按时间排序）
        $redis->zAdd($userKey, $messageData['createtime'], $messageId);
        
        // 添加到全局消息列表（供管理员查看）
        $redis->zAdd('customer_messages:all', $messageData['createtime'], $messageId);
        
        // 设置过期时间（30天）
//        $redis->expire($messageKey, 1 * 24 * 3600);
//        $redis->expire($userKey, 1 * 24 * 3600);
        
        // 执行事务
        $redis->exec();

        // 发送提问数据到队列进行异步处理
        $queueService = QueueService::getInstance();
        $queueService->publishCustomerMessage($messageData, $userId);
        // 发送用户操作日志数据到队列进行异步处理
        $userLogService = new UserLogService();
        // 获取更新后的用户信息
        $userInfo = $redis->hGetAll("user:{$userId}");
        $userLogService->publishUserLogToQueue([
            'title' => '用户提问',
            'user_id' => $userId,
            'username' => $userInfo['username'],
            'content' => json_encode($messageData, JSON_UNESCAPED_UNICODE),
        ]);
        ApiUtils::success('提交成功');
        
    } catch (Exception $e) {
        error_log('Redis Error: ' . $e->getMessage());
        ApiUtils::error('提交失败，请稍后重试', 500);
    }
}

/**
 * API使用示例:

1. 获取广告配置:
   POST http://jiankangpaika.blcwg.com/jkpk/api/customer_message.php

响应格式:
{
    "code": 200,
    "message": "提交成功",
    "timestamp": 1752411019,
    "datetime": "2025-07-13 20:50:19"
}
失败
{
    "code": 200,
    "message": "提交失败，请稍后重试",
    "timestamp": 1752411019,
    "datetime": "2025-07-13 20:50:19"
}
 */
?>