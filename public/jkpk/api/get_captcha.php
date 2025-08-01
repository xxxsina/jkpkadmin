<?php
/**
 * 获取图形验证码API接口
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

// 引入依赖
require_once __DIR__ . '/../utils/ApiUtils.php';
require_once __DIR__ . '/../utils/CaptchaUtils.php';

// 处理CORS
ApiUtils::handleCors();

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiUtils::error('只支持GET请求', 405);
}

/**
 * 处理获取验证码请求
 */
function handleGetCaptcha() {
    try {
        // 生成会话ID
        $sessionId = uniqid('uniqid_', true);
        
        // 生成图形验证码
        $captcha = CaptchaUtils::generateCaptcha();
        
        // 保存验证码到缓存
        CaptchaUtils::saveCaptcha($sessionId, $captcha['code']);
        
        // 返回结果
        ApiUtils::success('获取验证码成功', [
            'session_id' => $sessionId,
            'image' => 'data:image/png;base64,' . $captcha['image']
        ]);
        
    } catch (Exception $e) {
        ApiUtils::serverError('获取验证码失败: ' . $e->getMessage());
    }
}

// 执行处理
handleGetCaptcha();
?>