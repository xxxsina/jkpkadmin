<?php
/**
 * 发送短信验证码API接口
 * 包含防刷功能和图形验证码验证
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

// 引入依赖
require_once __DIR__ . '/../utils/ApiUtils.php';
require_once __DIR__ . '/../utils/CaptchaUtils.php';
require_once __DIR__ . '/../services/SmsService.php';

// 处理CORS
ApiUtils::handleCors();

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiUtils::error('只支持POST请求', 405);
}

/**
 * 定义参数验证规则
 */
function getSmsValidationRules() {
    return [
        'mobile' => 'required|phone',
//        'session_id' => 'required',
//        'captcha_code' => 'required'
    ];
}

/**
 * 处理发送短信请求
 */
function handleSendSms() {
    try {
        // 获取请求参数
        $params = ApiUtils::getRequestParams(['mobile', /*'session_id', 'captcha_code'*/], [
            'event' => 'login'
        ]);
        
        // 验证参数
        $rules = getSmsValidationRules();
        $errors = ApiUtils::validateParams($params, $rules);
        
        if (!empty($errors)) {
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                $errorMessages[] = implode(', ', $fieldErrors);
            }
            ApiUtils::error('参数错误: ' . implode('; ', $errorMessages), 400);
        }
        
        // 验证图形验证码
//        if (!CaptchaUtils::verifyCaptcha($params['session_id'], $params['captcha_code'])) {
//            ApiUtils::error('图形验证码错误！', 400);
//        }

        // 获取客户端IP
        $clientIp = ApiUtils::getClientIp();
        
        // 创建短信服务实例
        $smsService = new SmsService();
        
        // 发送短信验证码
        $result = $smsService->sendVerificationCode($params['mobile'], $params['event'], $clientIp);

        if ($result['success']) {
            ApiUtils::success($result['message'], $result['data']);
        } else {
            $extra = [];
            if (isset($result['wait_time'])) {
                $extra['wait_time'] = $result['wait_time'];
            }
            ApiUtils::error($result['message'], 429, null, $extra);
        }
        
    } catch (Exception $e) {
        ApiUtils::serverError('发送短信失败: ' . $e->getMessage());
    }
}

// 执行处理
handleSendSms();
?>