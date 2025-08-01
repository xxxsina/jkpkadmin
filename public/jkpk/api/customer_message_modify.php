<?php
/**
 * 客服消息修改接口
 * 处理客服消息状态修改（标记为已解决/未解决）
 * 数据存储在Redis中
 */

require_once __DIR__ . '/../utils/ApiUtils.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../services/UserLogService.php';
require_once __DIR__ . '/../services/QueueService.php';
require_once __DIR__ . '/../models/RedisModel.php';

// 处理CORS
ApiUtils::handleCors();

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// 只允许POST请求
if ($method !== 'POST') {
    ApiUtils::error('只支持POST请求', 405);
}

try {
    handleModifyMessage();
} catch (Exception $e) {
    error_log('Customer Message Modify API Error: ' . $e->getMessage());
    ApiUtils::error('服务器内部错误', 500);
}

/**
 * 处理消息修改
 */
function handleModifyMessage() {
    $redis = RedisModel::getInstance();

    // 获取请求参数
    $params = ApiUtils::getRequestParams(['user_id', 'id', 'is_overcome']);
    
    // 从header中获取token
    $token = ApiUtils::getTokenFromHeader();
    
    // 创建用户服务实例
    $userService = new UserService();
    $userId = $params['user_id'];
    $messageId = $params['id'];
    $isOvercome = intval($params['is_overcome']);
    
    // 验证用户令牌
    if (!$userService->validateUserToken($userId, $token)) {
        ApiUtils::unauthorized('登录已过期，请退出后重新登陆');
    }

    // 验证参数
    if (empty($messageId)) {
        ApiUtils::error('消息ID不能为空', 400);
        return;
    }
    
    // 验证is_overcome参数（0或1）
    if (!in_array($isOvercome, [2, 1])) {
        ApiUtils::error('解决参数值无效', 400);
        return;
    }
    
    // 构建消息键
    $messageKey = "customer_messages:userId:{$userId}:msgId:{$messageId}";

    try {
        // 检查消息是否存在
        if (!$redis->exists($messageKey)) {
            throw new Exception("消息不存在或已过期", 404);
        }

        // 获取当前消息数据
        $messageData = $redis->hGetAll($messageKey);
        
        // 验证消息是否属于当前用户
        if ($messageData['user_id'] != $userId) {
            throw new Exception("无权限修改此消息", 403);
        }
        
        // 更新消息状态
        $updateData = [
            'is_overcome' => $isOvercome,
            'updatetime' => time()
        ];
        
        // 使用Redis事务确保数据一致性
        $redis->multi();
        
        // 更新消息数据
        $redis->hMSet($messageKey, $updateData);
        
        // 执行事务
        $redis->exec();

        // 发送提问数据到队列进行异步处理
        $queueService = QueueService::getInstance();
        $updateData['id'] = $messageId;
        $queueService->publishCustomerMessage($updateData, $userId, 'update');

        // 记录用户操作日志
        $userLogService = new UserLogService();
        $userInfo = $redis->hGetAll("user:{$userId}");
        $overComeText = $isOvercome == 1 ? '已解决' : '未解决';
        
        $userLogService->publishUserLogToQueue([
            'title' => '修改提问消息状态',
            'user_id' => $userId,
            'username' => $userInfo['username'] ?? '',
            'content' => json_encode([
                'message_id' => $messageId,
                'over_come_text' => $overComeText,
                'is_overcome' => $isOvercome
            ], JSON_UNESCAPED_UNICODE),
        ]);
        
        ApiUtils::success('提交成功');
        
    } catch (Exception $e) {
        error_log('Redis Error: ' . $e->getMessage());
        ApiUtils::error($e->getMessage(), $e->getCode());
    }
}

/**
 * API使用示例:

1. 修改客服消息状态:
   POST http://jiankangpaika.blcwg.com/jkpk/api/customer_message_modify.php
   
   Headers:
   Content-Type: application/json
   Authorization: Bearer YOUR_TOKEN_HERE
   
   Body (JSON):
   {
       "user_id": "123",
       "id": "456",
       "is_overcome": "1"
   }
   
   或者使用表单提交:
   Content-Type: application/x-www-form-urlencoded
   
   Body:
   user_id=123&id=456&is_overcome=1

成功响应:
{
    "code": 200,
    "message": "修改成功",
    "timestamp": 1752411019,
    "datetime": "2025-07-13 20:50:19"
}

失败响应:
{
    "code": 400,
    "message": "消息ID不能为空",
    "timestamp": 1752411019,
    "datetime": "2025-07-13 20:50:19"
}

{
    "code": 401,
    "message": "登录已过期，请退出后重新登陆",
    "timestamp": 1752411019,
    "datetime": "2025-07-13 20:50:19"
}

{
    "code": 404,
    "message": "消息不存在或已过期",
    "timestamp": 1752411019,
    "datetime": "2025-07-13 20:50:19"
}

{
    "code": 403,
    "message": "无权限修改此消息",
    "timestamp": 1752411019,
    "datetime": "2025-07-13 20:50:19"
}
 */
?>