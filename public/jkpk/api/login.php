<?php
/**
 * 用户登录API接口
 * 基于ApiUtils架构重构
 * 
 * @author 健康派卡开发团队
 * @version 3.0
 * @date 2024-01-01
 */

// 引入依赖
require_once __DIR__ . '/../utils/ApiUtils.php';
require_once __DIR__ . '/../services/UserService.php';

// 处理CORS
ApiUtils::handleCors();

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiUtils::error('只支持POST请求', 405);
}

/**
 * 定义登录参数验证规则
 */
function getLoginValidationRules() {
    return [
        'username' => 'required|account',
        'password' => 'required'
    ];
}

/**
 * 处理登录请求
 */
function handleLogin() {
    try {
        // 获取请求参数
        $params = ApiUtils::getRequestParams(['username', 'password']);

        // 验证参数
        $rules = getLoginValidationRules();
        $errors = ApiUtils::validateParams($params, $rules);
        
        if (!empty($errors)) {
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                $errorMessages[] = implode(', ', $fieldErrors);
            }
            ApiUtils::error('失败: ' . implode('; ', $errorMessages), 400);
        }

        // 调用用户服务进行登录
        $userService = new UserService();
        $result = $userService->login($params['username'], $params['password']);

        if ($result['success']) {
            // 记录成功日志并返回结果
            ApiUtils::logApiAccess('login', $params, $result);
            ApiUtils::success( '登录成功', $result['data']);
        } else {
            // 记录失败日志并返回错误
            ApiUtils::logApiAccess('login', $params, null, $result['message']);
            ApiUtils::error($result['message'], 401);
        }
        
    } catch (Exception $e) {
        // 记录系统错误日志
        ApiUtils::logApiAccess('login', $params ?? [], null, $e->getMessage());
        ApiUtils::error('系统错误，请稍后重试: ' . $e->getMessage(), 500);
    }
}

// 执行登录处理
handleLogin();

/**
 * API使用说明
 * 
 * 接口地址: /login_api.php
 * 请求方式: POST
 * 请求格式: JSON
 * 
 * 请求参数:
 * - username (string, 必填): 账号，支持用户名、邮箱、手机号
 * - password (string, 必填): 密码
 * 
 * 请求示例:
 * {
 *     "username": "testuser",
 *     "password": "123456"
 * }
 * 
 * 响应格式:
 * {
 *     "success": true|false,
 *     "message": "响应消息",
 *     "data": {
 *         "user_id": "用户ID",
 *         "token": "登录令牌",
 *         "user_info": {
 *             "username": "用户名",
 *             "nickname": "昵称",
 *             "phone": "手机号",
 *             "email": "邮箱",
 *             "avatar": "头像URL",
 *             "status": "用户状态",
 *             "created_at": "注册时间"
 *         }
 *     },
 *     "timestamp": "响应时间",
 *     "request_id": "请求ID"
 * }
 * 
 * 错误响应:
 * {
 *     "success": false,
 *     "message": "错误信息",
 *     "error_code": "错误代码",
 *     "timestamp": "响应时间",
 *     "request_id": "请求ID"
 * }
 * 
 * 支持的登录方式:
 * - 用户名 + 密码
 * - 邮箱 + 密码
 * - 手机号 + 密码
 * 
 * 测试账号:
 * - admin / 123456 (管理员账号)
 * - testuser / 123456 (测试用户)
 * - demo / 123456 (演示账号)
 * - 13800138000 / 123456 (手机号登录)
 * - admin@test.com / 123456 (邮箱登录)
 * 
 * 登录流程:
 * 1. 验证参数格式
 * 2. 检查账号是否存在
 * 3. 验证密码是否正确
 * 4. 检查账号状态
 * 5. 生成登录令牌
 * 6. 更新登录时间
 * 7. 返回用户信息和令牌
 * 
 * 安全特性:
 * - 密码加密存储
 * - 登录失败次数限制
 * - Token有效期管理
 * - 登录日志记录
 * - IP地址记录
 * 
 * 错误代码说明:
 * - 400: 参数错误
 * - 401: 认证失败（用户名或密码错误）
 * - 403: 账号被禁用
 * - 429: 登录尝试过于频繁
 * - 500: 系统错误
 */
?>