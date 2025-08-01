<?php
/**
 * 客服消息列表接口
 * 获取用户的客服消息列表，支持分页
 * 使用Redis有序集合存储，按时间倒序返回
 */

require_once __DIR__ . '/../utils/ApiUtils.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../models/RedisModel.php';
$config = require_once __DIR__ . '/../config/config.php';

// 处理CORS
ApiUtils::handleCors();

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        handleGetMessageList();
    } else {
        ApiUtils::error('不支持的请求方法', 405);
    }
} catch (Exception $e) {
    error_log('Customer Message List API Error: ' . $e->getMessage());
    ApiUtils::error('服务器内部错误', 500);
}

/**
 * 处理获取消息列表请求
 */
function handleGetMessageList() {
    $redis = RedisModel::getInstance();
    
    // 获取请求参数
    $params = ApiUtils::getRequestParams(['user_id', 'page']);
    
    // 从header中获取token
    $token = ApiUtils::getTokenFromHeader();
    
    // 创建用户服务实例
    $userService = new UserService();
    
    // 验证用户令牌
    if (!$userService->validateUserToken($params['user_id'], $token)) {
        ApiUtils::unauthorized('登录已过期，请退出后重新登陆');
    }
    
    // 验证页数参数
    $page = isset($params['page']) ? intval($params['page']) : 1;
    if ($page < 1) {
        $page = 1;
    }
    
    // 每页显示10条
    $pageSize = 10;
    $start = ($page - 1) * $pageSize;
    $end = $start + $pageSize - 1;
    
    try {
        // 获取用户消息列表的key
        $userKey = "customer_messages:user:{$params['user_id']}";
        
        // 使用zRevRange获取消息ID列表（按时间倒序）
        $messageIds = $redis->zRevRange($userKey, $start, $end);
        
        $messages = [];
        
        // 获取每条消息的详细信息
        foreach ($messageIds as $messageId) {
            $messageKey = "customer_messages:userId:{$params['user_id']}:msgId:{$messageId}";
            $messageData = $redis->hGetAll($messageKey);
            
            if (!empty($messageData)) {
                // 格式化时间字段
                if (isset($messageData['createtime'])) {
                    $messageData['createtime_formatted'] = date('Y-m-d H:i', $messageData['createtime']);
                }
                if (isset($messageData['updatetime'])) {
                    $messageData['updatetime_formatted'] = date('Y-m-d H:i', $messageData['updatetime']);
                }
                
                // 格式化媒体文件URL
                $messageData['image'] = formatMediaUrl($messageData['image'] ?? '', 'image');
                $messageData['video'] = formatMediaUrl($messageData['video'] ?? '', 'video');

                $messageData['is_overcome'] = intval($messageData['is_overcome']);
                $messageData['answer_image'] = formatMediaUrlAdmin($messageData['answer_image'] ?? '', 'image');
                $messageData['answer_video'] = formatMediaUrlAdmin($messageData['answer_video'] ?? '', 'video');

                // 转换数值字段
                $messageData['id'] = intval($messageData['id']);
                $messageData['user_id'] = intval($messageData['user_id']);
                $messageData['looked'] = intval($messageData['looked']);
//                $messageData['answer'] = "我是回复占位符";
                $messageData['createtime'] = intval($messageData['createtime']);
                $messageData['updatetime'] = intval($messageData['updatetime']);
                
                $messages[] = $messageData;
            }
        }
        
        // 获取总数量
        $totalCount = $redis->zCard($userKey);
        $totalPages = ceil($totalCount / $pageSize);
        
        // 返回结果
        $result = [
            'list' => $messages,
            'pagination' => [
                'current_page' => $page,
                'page_size' => $pageSize,
                'total_count' => $totalCount,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ];
        
        ApiUtils::success('获取成功', $result);
        
    } catch (Exception $e) {
        error_log('Redis Error: ' . $e->getMessage());
        ApiUtils::error('获取消息列表失败，请稍后重试', 500);
    }
}

/**
 * 格式化媒体文件URL
 * @param string $mediaUrl 媒体文件URL
 * @param string $type 媒体类型 (image/video)
 * @return string 格式化后的URL
 */
function formatMediaUrl($mediaUrl, $type = 'image') {
    global $config;
    if (empty($mediaUrl)) {
        return '';
    }

    // 外部URL，直接返回
    if (filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
        return $mediaUrl;
    }

    // 本地文件，返回完整HTTP地址
    $basePath = $type === 'video' ? '/data/videos/' : '/data/images/';
    return $config['HTTP_HOST'] . $basePath . $mediaUrl;
}

/**
 * ADMIN格式化媒体文件URL
 * @param string $mediaUrl 媒体文件URL
 * @param string $type 媒体类型 (image/video)
 * @return string 格式化后的URL
 */
function formatMediaUrlAdmin($mediaUrl, $type = 'image') {
    global $config;
    if (empty($mediaUrl)) {
        return '';
    }

    // 外部URL，直接返回
    if (filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
        return $mediaUrl;
    }

    // 本地文件，返回完整HTTP地址
    return $config['HTTP_HOST_ADMIN'] . $mediaUrl;
}

/**
 * API使用示例:

1. 获取消息列表:
   POST http://jiankangpaika.blcwg.com/jkpk/api/customer_message_list.php
   Headers: Authorization: Bearer {token}
   Body: {
       "user_id": 123,
       "page": 1
   }

响应格式:
{
    "code": 200,
    "message": "获取成功",
    "data": {
        "list": [
            {
                "id": 1,
                "user_id": 123,
                "status": "new",// 提问状态，new新问题，answer已回答
                "looked": 0,// 管理员是否查看，1是，0否
                "realname": "张三",// 用户姓名
                "mobile": "13800138000",// 用户手机号
                "problem": "问题描述",// 用户提问的内容
                "answer": "",// 管理员回答的内容
                "image": "",// 用户提问的图片
                "video": "",// 用户提问的视频
                "answer_image": "", // 管理员回答的图片
                "answer_video": "", // 管理员回答的视频
                "is_overcome": 0,   // 是否解决，用户点击，1是，0否
                "createtime": 1752411019,
                "updatetime": 1752411019,
                "createtime_formatted": "2025-07-13 20:50:19",
                "updatetime_formatted": "2025-07-13 20:50:19"
            }
        ],
        "pagination": {
            "current_page": 1,
            "page_size": 10,
            "total_count": 15,
            "total_pages": 2,
            "has_next": true,
            "has_prev": false
        }
    },
    "timestamp": 1752411019,
    "datetime": "2025-07-13 20:50:19"
}

失败响应:
{
    "code": 401,
    "message": "登录已过期，请退出后重新登陆",
    "timestamp": 1752411019,
    "datetime": "2025-07-13 20:50:19"
}
 */
?>