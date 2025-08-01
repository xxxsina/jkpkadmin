<?php
/**
 * 短信服务类
 * 处理短信发送、验证码管理、防刷逻辑
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

require_once __DIR__ . '/../utils/SmsUtils.php';
require_once __DIR__ . '/../models/RedisModel.php';

class SmsService {
    private $redis;

    private static $config;
    
    public function __construct() {
        $this->redis = RedisModel::getInstance();
        $configFile = __DIR__ . '/../config/config.php';
        $allConfig = require $configFile;
        self::$config = $allConfig['sms_config'];
    }
    
    /**
     * 检查发送频率限制
     * 
     * @param string $phone 手机号
     * @param string $ip IP地址
     * @return array
     */
    public function checkSendLimit($phone, $ip) {
        $currentTime = time();
        
        // 检查手机号60秒内是否已发送
        $phoneKey = "sms_limit_phone_{$phone}";
        $lastSendTime = $this->redis->get($phoneKey);
        
        if ($lastSendTime && ($currentTime - $lastSendTime) < self::$config['time_limit']) {
            return [
                'allowed' => false,
                'message' => '发送过于频繁，请稍后再试',
                'wait_time' => self::$config['time_limit'] - ($currentTime - $lastSendTime)
            ];
        }
        
        // 检查IP地址每小时发送次数（最多10次）
        $ipKey = "sms_limit_ip_{$ip}";
        $ipCount = $this->redis->get($ipKey) ?: 0;
        
        if ($ipCount >= self::$config['ip_limit']) {
            return [
                'allowed' => false,
                'message' => 'IP发送次数过多，请稍后再试',
                'wait_time' => 3600
            ];
        }
        
        return ['allowed' => true];
    }
    
    /**
     * 更新发送限制记录
     * 
     * @param string $phone 手机号
     * @param string $ip IP地址
     */
    public function updateSendLimit($phone, $ip) {
        $currentTime = time();
        
        // 记录手机号发送时间
        $phoneKey = "sms_limit_phone_{$phone}";
        $this->redis->set($phoneKey, $currentTime, 60);
        
        // 增加IP发送次数
        $ipKey = "sms_limit_ip_{$ip}";
        $this->redis->incr($ipKey);
        $this->redis->expire($ipKey, 3600);
    }
    
    /**
     * 保存验证码到Redis
     * 
     * @param string $phone 手机号
     * @param string $code 验证码
     * @param string $event 事件类型
     * @param string $ip IP地址
     * @return bool 保存是否成功
     */
    public function saveSmsCode($phone, $code, $event = 'login', $ip = '') {
        $currentTime = time();
        
        // 使用Redis保存验证码，key格式：sms_code:{event}:{phone}
        $key = "sms_code:{$event}:{$phone}";
        
        // 保存验证码信息到Redis，包含验证码、创建时间、IP等信息
        $data = [
            'code' => $code,
            'event' => $event,
            'mobile' => $phone,
            'ip' => $ip,
            'createtime' => $currentTime,
            'times' => 0  // 使用次数
        ];
        
        // 使用set方法保存数据
        $result = $this->redis->set($key, json_encode($data), 300);

        return $result !== false;
    }
    
    /**
     * 验证短信验证码
     * 
     * @param string $phone 手机号
     * @param string $code 验证码
     * @param string $event 事件类型
     * @param int $expireTime 过期时间（秒）
     * @return array
     */
    public function verifySmsCode($phone, $code, $event = 'login', $expireTime = 300) {
        $key = "sms_code:{$event}:{$phone}";
        
        // 从Redis获取验证码信息
        $data = $this->redis->get($key);

        if (empty($data)) {
            return [
                'valid' => false,
                'message' => '短信验证码获取失败，请重试'
            ];
        }

        if (empty($data['code'])) {
            return [
                'valid' => false,
                'message' => '短信验证码不存在或已过期'
            ];
        }
        
        // 检查验证码是否正确
        if ($data['code'] !== $code) {
            return [
                'valid' => false,
                'message' => '短信验证码错误'
            ];
        }
        
        // 检查是否已过期（虽然Redis会自动过期，但这里再次检查确保安全）
        $currentTime = time();
        if (($currentTime - $data['createtime']) > $expireTime) {
            // 删除过期的验证码
            $this->redis->del($key);
            return [
                'valid' => false,
                'message' => '短信验证码已过期'
            ];
        }

        // 重新设置剩余的过期时间
        $remainingTime = $expireTime - ($currentTime - $data['createtime']);
        // 验证成功，增加使用次数并更新Redis
        $data['times'] += 1;
        $this->redis->set($key, json_encode($data), $remainingTime);
        
        return [
            'valid' => true,
            'message' => '短信验证码正确',
            'data' => $data
        ];
    }
    
    /**
     * 发送短信验证码
     * 
     * @param string $phone 手机号
     * @param string $event 事件类型
     * @param string $ip IP地址
     * @return array
     */
    public function sendVerificationCode($phone, $event = 'login', $ip = '') {
        // 验证手机号格式
        if (!SmsUtils::validatePhone($phone)) {
            return [
                'success' => false,
                'message' => '手机号格式不正确'
            ];
        }
        
        // 检查发送限制
        $limitCheck = $this->checkSendLimit($phone, $ip);
        if (!$limitCheck['allowed']) {
            return [
                'success' => false,
                'message' => $limitCheck['message'],
                'wait_time' => $limitCheck['wait_time'] ?? 0
            ];
        }
        
        // 生成验证码
        $code = SmsUtils::generateCode(6);
        
        // 发送短信
        $smsResult = SmsUtils::sendSms($phone, $code);
//        $smsResult = true;

        if (!$smsResult) {
            return [
                'success' => false,
                'message' => '短信发送失败，请稍后重试'
            ];
        }
        
        // 保存验证码
        $codeId = $this->saveSmsCode($phone, $code, $event, $ip);
        
        if (!$codeId) {
            return [
                'success' => false,
                'message' => '验证码保存失败'
            ];
        }
        
        // 更新发送限制
        $this->updateSendLimit($phone, $ip);
        
        return [
            'success' => true,
            'message' => '验证码发送成功',
            'data' => [
                'phone' => $phone,
                'code_id' => $codeId,
            ]
        ];
    }
}
?>