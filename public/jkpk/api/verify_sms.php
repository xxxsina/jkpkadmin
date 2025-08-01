<?php
/**
 * 验证短信验证码API接口
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

// 引入依赖
require_once __DIR__ . '/../utils/ApiUtils.php';
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
function getVerifyValidationRules() {
    return [
        'mobile' => 'required|phone',
        'sms_code' => 'required'
    ];
}

/**
 * 处理验证短信验证码请求
 */
function handleVerifySms() {
    try {
        // 获取请求参数
        $params = ApiUtils::getRequestParams(['mobile', 'sms_code'], [
            'event' => 'login'
        ]);

        // 验证参数
        $rules = getVerifyValidationRules();
        $errors = ApiUtils::validateParams($params, $rules);
        
        if (!empty($errors)) {
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                $errorMessages[] = implode(', ', $fieldErrors);
            }
            ApiUtils::error('参数错误: ' . implode('; ', $errorMessages), 400);
        }
        
        // 创建短信服务实例
        $smsService = new SmsService();
        
        // 验证短信验证码
        $result = $smsService->verifySmsCode($params['mobile'], $params['sms_code'], $params['event']);
        
        if ($result['valid']) {
            ApiUtils::success($result['message'], $result['data'] ?? null);
        } else {
            ApiUtils::error($result['message'], 400);
        }
        
    } catch (Exception $e) {
        ApiUtils::serverError('验证失败: ' . $e->getMessage());
    }
}

// 执行处理
handleVerifySms();
?>