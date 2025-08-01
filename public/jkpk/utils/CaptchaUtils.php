<?php
/**
 * 图形验证码工具类
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

class CaptchaUtils {
    
    /**
     * 生成图形验证码
     * 
     * @param int $width 宽度
     * @param int $height 高度
     * @param int $length 验证码长度
     * @return array
     */
    public static function generateCaptcha($width = 120, $height = 40, $length = 4) {
        // 创建画布
        $image = imagecreate($width, $height);
        
        // 设置颜色
        $bgColor = imagecolorallocate($image, 255, 255, 255);
        $textColor = imagecolorallocate($image, 0, 0, 0);
        $lineColor = imagecolorallocate($image, 128, 128, 128);
        
        // 填充背景
        imagefill($image, 0, 0, $bgColor);
        
        // 生成验证码字符
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $char = $chars[mt_rand(0, strlen($chars) - 1)];
            $code .= $char;
            
            // 在图片上绘制字符
            $x = ($width / $length) * $i + 10;
            $y = mt_rand($height / 2, $height - 10);
            imagestring($image, 5, $x, $y, $char, $textColor);
        }
        
        // 添加干扰线
        for ($i = 0; $i < 5; $i++) {
            imageline($image, mt_rand(0, $width), mt_rand(0, $height), 
                     mt_rand(0, $width), mt_rand(0, $height), $lineColor);
        }
        
        // 输出图片
        ob_start();
        imagepng($image);
        $imageData = ob_get_contents();
        ob_end_clean();
        
        // 清理资源
        imagedestroy($image);
        
        return [
            'code' => $code,
            'image' => base64_encode($imageData)
        ];
    }
    
    /**
     * 验证图形验证码
     * 
     * @param string $sessionId 会话ID
     * @param string $inputCode 用户输入的验证码
     * @return bool
     */
    public static function verifyCaptcha($sessionId, $inputCode) {
        require_once __DIR__ . '/../models/RedisModel.php';
        
        $redis = RedisModel::getInstance();
        $key = "captcha_{$sessionId}";
        $storedCode = $redis->get($key);
        
        if (!$storedCode) {
            return false;
        }
        
        // 验证后删除验证码
        $redis->delete($key);
        
        return strtoupper($inputCode) === strtoupper($storedCode);
    }
    
    /**
     * 保存验证码到缓存
     * 
     * @param string $sessionId 会话ID
     * @param string $code 验证码
     * @param int $expireTime 过期时间（秒）
     */
    public static function saveCaptcha($sessionId, $code, $expireTime = 300) {
        require_once __DIR__ . '/../models/RedisModel.php';
        
        $redis = RedisModel::getInstance();
        $key = "captcha_{$sessionId}";
        $redis->set($key, $code, $expireTime);
    }
}
?>