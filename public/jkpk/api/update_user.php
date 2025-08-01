<?php
/**
 * 用户数据修改接口
 * 提供修改用户昵称、头像、手机号、邮箱等功能
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../utils/ApiUtils.php';

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiUtils::error('只支持POST请求', 405);
}

try {
    $startTime = microtime(true);
    
    // 获取请求参数
    $params = ApiUtils::getRequestParams(
        ['action', 'user_id'], // 必需参数：操作类型和用户ID
        ['value' => '', 'smsCode' => '', 'event' => ''] // 可选参数
    );
    
    $action = $params['action'];
    $userId = $params['user_id'];
    $value = $params['value'];
    
    // 从header中获取token
    $token = ApiUtils::getTokenFromHeader();
    
    // 验证基本参数
    if (empty($userId)) {
        ApiUtils::paramError('用户ID不能为空');
    }
    
    if (empty($token)) {
        ApiUtils::unauthorized('缺少访问令牌，请在请求头中提供token');
    }
    
    // 对于非头像修改操作和邮箱解绑操作，value不能为空
    if ($action !== 'update_avatar' && empty($value)) {
        // 如果是邮箱解绑操作，允许value为空
        if ($action === 'update_email' && ($params['event'] ?? '') === 'unbind') {
            // 邮箱解绑操作，允许value为空
        } else {
            ApiUtils::paramError('修改值不能为空');
        }
    }
    
    // 创建用户服务实例
    $userService = new UserService();
    
    // 验证用户令牌（简单验证）
    if (!$userService->validateUserToken($userId, $token)) {
        ApiUtils::unauthorized('登录已过期，请退出后重新登陆');
    }
    
    // 根据操作类型执行相应的修改
    switch ($action) {
        case 'update_nickname':
            $result = $userService->updateNickname($userId, $value);
            break;
            
        case 'update_avatar':
            $result = $userService->updateAvatar($userId, $value);
            break;
            
        case 'update_phone':
            $mobile = $value;
            $smsCode = $params['smsCode'] ?? '';
            $event = $params['event'] ?? 'bind'; // bind 或 unbind
            
            // 如果提供了短信验证码，使用带验证的方法
            if (!empty($smsCode)) {
                $result = $userService->updatePhoneWithSms($userId, $mobile, $smsCode, $event);
            } else {
                ApiUtils::paramError('短信验证码不能为空');
            }
            break;
            
        case 'update_email':
            $event = $params['event'] ?? 'bind'; // bind 或 unbind
            $result = $userService->updateEmail($userId, $value, $event);
            break;
            
        default:
            ApiUtils::paramError('不支持的操作类型: ' . $action);
    }
    
    // 记录API访问日志
    $duration = microtime(true) - $startTime;
    $logParams = array_merge($params, ['value' => '***']); // 隐藏敏感信息
    ApiUtils::logApiAccess('update_user_api', $logParams, $result['success'] ? 'success' : 'failed', $duration);
    
    // 返回结果
    if ($result['success']) {
        ApiUtils::success($result['message'], $result['data'] ?? null);
    } else {
        ApiUtils::error($result['message'], $result['code'] ?? 400);
    }
    
} catch (Exception $e) {
    // 记录错误日志
    error_log("[UpdateUserAPI] 处理请求时发生错误: " . $e->getMessage());
    
    // 返回服务器错误
    ApiUtils::serverError('服务器内部错误，请稍后重试');
}