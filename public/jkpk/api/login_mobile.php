<?php
/**
 * 手机短信登录API接口
 * 基于ApiUtils架构实现
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

// 引入依赖
require_once __DIR__ . '/../utils/ApiUtils.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../services/SmsService.php';

// 处理CORS
ApiUtils::handleCors();

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiUtils::error('只支持POST请求', 405);
}

/**
 * 定义手机短信登录参数验证规则
 */
function getMobileLoginValidationRules() {
    return [
        'mobile' => 'required|phone',
        'sms_code' => 'required',
//        'captcha_code' => 'required',
//        'session_id' => 'required'
    ];
}

/**
 * 处理手机短信登录请求
 */
function handleMobileLogin() {
    try {
        // 获取请求参数
        $params = ApiUtils::getRequestParams(['mobile', 'sms_code', /*'captcha_code', 'session_id'*/]);

        // 验证参数
        $rules = getMobileLoginValidationRules();
        $errors = ApiUtils::validateParams($params, $rules);
        
        if (!empty($errors)) {
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                $errorMessages[] = implode(', ', $fieldErrors);
            }
            ApiUtils::error('失败: ' . implode('; ', $errorMessages), 400);
        }

        // 创建服务实例
        $userService = new UserService();
        $smsService = new SmsService();
        
        // 验证短信验证码
        $smsResult = $smsService->verifySmsCode($params['mobile'], $params['sms_code'], 'login');
        
        if (!$smsResult['valid']) {
            ApiUtils::logApiAccess('login_mobile', $params, null, $smsResult['message']);
            ApiUtils::error($smsResult['message'], 400);
        }
        
        // 调用用户服务进行手机短信登录
        $result = $userService->mobileLogin($params['mobile'], $params['sms_code'], $params['captcha_code'], $params['session_id']);

        if ($result['success']) {
            // 记录成功日志并返回结果
            ApiUtils::logApiAccess('login_mobile', $params, $result);
            ApiUtils::success('登录成功', $result['data']);
        } else {
            // 记录失败日志并返回错误
            ApiUtils::logApiAccess('login_mobile', $params, null, $result['message']);
            ApiUtils::error($result['message'], $result['code'] ?? 401);
        }
        
    } catch (Exception $e) {
        // 记录系统错误日志
        ApiUtils::logApiAccess('login_mobile', $params ?? [], null, $e->getMessage());
        ApiUtils::error('系统错误，请稍后重试: ' . $e->getMessage(), 500);
    }
}

// 执行登录处理
handleMobileLogin();

/**
 * API使用说明
 * 
 * 接口地址: /login_mobile.php
 * 请求方式: POST
 * 请求格式: JSON
 * 
 * 请求参数:
 * - mobile (string, 必填): 手机号码
 * - sms_code (string, 必填): 短信验证码
 * - captcha_code (string, 必填): 图形验证码
 * - session_id (string, 必填): 验证码会话ID
 * 
 * 请求示例:
 * {
 *     "mobile": "13800138000",
 *     "sms_code": "123456",
 *     "captcha_code": "abcd",
 *     "session_id": "session_123456"
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
 *             "id": "用户ID",
 *             "username": "用户名",
 *             "nickname": "昵称",
 *             "mobile": "手机号",
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
 * 登录流程:
 * 1. 验证参数格式
 * 2. 验证短信验证码
 * 3. 检查手机号是否已注册
 * 4. 如果未注册，自动创建账号
 * 5. 绑定手机号到用户账号
 * 6. 生成登录令牌
 * 7. 更新登录时间和IP
 * 8. 记录登录日志到队列
 * 9. 返回用户信息和令牌
 * 
 * 特性:
 * - 支持未注册手机号自动创建账号
 * - 自动绑定手机号到用户账号
 * - 异步队列记录登录日志
 * - 异步队列更新数据库
 * - Token有效期管理
 * - IP地址记录
 * 
 * 错误代码说明:
 * - 400: 参数错误或验证码错误
 * - 401: 认证失败
 * - 403: 账号被禁用
 * - 429: 登录尝试过于频繁
 * - 500: 系统错误
 */
?>