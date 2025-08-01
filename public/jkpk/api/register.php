<?php
/**
 * 用户注册API接口
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
 * 定义注册参数验证规则
 */
function getRegisterValidationRules() {
    return [
        'username' => 'required|account',
        'password' => 'required|password',
        'confirm_password' => 'required'
    ];
}

/**
 * 验证确认密码
 */
function validateConfirmPassword($params) {
    if (!isset($params['password']) || !isset($params['confirm_password'])) {
        return false;
    }
    return $params['password'] === $params['confirm_password'];
}

/**
 * 处理注册请求
 */
function handleRegister() {
    try {
        // 获取请求参数
        $params = ApiUtils::getRequestParams(['username', 'password', 'confirm_password']);
        
        // 验证基础参数
        $rules = getRegisterValidationRules();
        $errors = ApiUtils::validateParams($params, $rules);
        
        if (!empty($errors)) {
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                $errorMessages[] = implode(', ', $fieldErrors);
            }
            ApiUtils::error('失败: ' . implode('; ', $errorMessages), 400);
        }
        
        // 验证确认密码
        if (!validateConfirmPassword($params)) {
            ApiUtils::error('两次输入的密码不一致', 400);
        }

        // 调用用户服务进行注册
        $userService = new UserService();
        $result = $userService->register($params);
        
        if ($result['success']) {
            // 记录成功日志并返回结果
            ApiUtils::logApiAccess('register', $params, $result);
            ApiUtils::success( '注册成功', $result['data']);
        } else {
            // 记录失败日志并返回错误
            ApiUtils::logApiAccess('register', $params, null, $result['message']);
            ApiUtils::error($result['message'], 400);
        }
        
    } catch (Exception $e) {
        // 记录系统错误日志
        ApiUtils::logApiAccess('register', $params ?? [], null, $e->getMessage());
        ApiUtils::error('系统错误，请稍后重试: ' . $e->getMessage(), 500);
    }
}

// 执行注册处理
handleRegister();
?>