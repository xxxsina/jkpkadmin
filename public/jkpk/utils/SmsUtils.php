<?php
/**
 * 短信工具类
 * 提供短信发送、验证码生成等功能
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

class SmsUtils {
    
    private static $config;
    
    /**
     * 初始化配置
     */
    private static function initConfig() {
        if (self::$config === null) {
            $configFile = __DIR__ . '/../config/config.php';
            $allConfig = require $configFile;
            self::$config = $allConfig['sms_config'];
        }
    }
    
    /**
     * 执行CURL请求
     * 
     * @param string $url 请求URL
     * @param array $data 请求数据
     * @param array $headers 请求头
     * @param bool $isJson 是否JSON格式
     * @return array|false
     */
    public static function curlRequest($url, $data = [], $headers = [], $isJson = false) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if ($isJson) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $httpCode !== 200) {
            return false;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * 获取授权TOKEN
     * 
     * @return string|false
     */
    public static function getAccessToken() {
        self::initConfig();
        
        $tokenUrl = self::$config['api_host'] . '/v2/user/login';
        
        $data = [
            'access_key' => self::$config['app_id'],
            'secret_key' => self::$config['app_secret']
        ];
        
        $result = self::curlRequest($tokenUrl, $data);
        
        if ($result && isset($result['status']) && $result['status'] == 200 && isset($result['data']['access_token'])) {
            return $result['data']['access_token'];
        }
        
        return false;
    }
    
    /**
     * 发送短信
     * 
     * @param string $phone 手机号
     * @param string $code 验证码
     * @return bool
     */
    public static function sendSms($phone, $code) {
        self::initConfig();
        
        // 获取授权token
        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        // 准备请求数据
        $data = [
            'phone' => $phone,
            'temp_id' => self::$config['temp_id'],
            'param' => json_encode(['code' => $code, 'time' => 5])
        ];

        $apiUrl = self::$config['api_host'] . '/v2/sms_v2/send';
        $headers = ['Authorization: Bearer-' . $accessToken];
        
        $result = self::curlRequest($apiUrl, $data, $headers, true);
        
        return $result && isset($result['status']) && $result['status'] == 200;
    }
    
    /**
     * 生成验证码
     * 
     * @param int $length 验证码长度
     * @return string
     */
    public static function generateCode($length = 6) {
        // 确保生成指定位数的验证码，避免出现3位数等情况
        $min = pow(10, $length - 1); // 最小值：100000（6位）
        $max = pow(10, $length) - 1; // 最大值：999999（6位）
        return (string) mt_rand($min, $max);
    }
    
    /**
     * 验证手机号格式
     * 
     * @param string $phone 手机号
     * @return bool
     */
    public static function validatePhone($phone) {
        return preg_match('/^1[3-9]\d{9}$/', $phone);
    }
}
?>