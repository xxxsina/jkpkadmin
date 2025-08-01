<?php
/**
 * 用户退出登录接口
 * 提供用户退出登录功能，清除用户会话
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
        ['user_id'], // 必需参数：用户ID
        [] // 无可选参数
    );
    
    $userId = $params['user_id'];
    
    // 从header中获取token
    $token = ApiUtils::getTokenFromHeader();
    
    // 验证基本参数
    if (empty($userId)) {
        ApiUtils::paramError('用户ID不能为空');
    }
    
    if (empty($token)) {
        ApiUtils::unauthorized('缺少访问令牌，请在请求头中提供token');
    }
    
    // 创建用户服务实例
    $userService = new UserService();
    
    // 验证用户令牌
    if (!$userService->validateUserToken($userId, $token)) {
        ApiUtils::unauthorized('登录已过期，请退出后重新登陆');
    }
    
    // 执行退出登录操作
    $result = $userService->logout($userId);
    
    // 记录API访问日志
    $duration = microtime(true) - $startTime;
    $logParams = ['user_id' => $userId, 'token' => '***']; // 隐藏敏感信息
    ApiUtils::logApiAccess('logout_api', $logParams, $result['success'] ? 'success' : 'failed', $duration);
    
    // 返回结果
    if ($result['success']) {
        ApiUtils::success($result['message'], $result['data'] ?? null);
    } else {
        ApiUtils::error($result['message'], $result['code'] ?? 400);
    }
    
} catch (Exception $e) {
    // 记录错误日志
    error_log("[LogoutAPI] 处理请求时发生错误: " . $e->getMessage());
    
    // 返回服务器错误
    ApiUtils::serverError('服务器内部错误，请稍后重试');
}