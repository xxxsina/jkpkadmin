<?php
/**
 * 基础Worker类
 * 提供所有Worker的公共方法和属性
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

require_once __DIR__ . '/../services/QueueService.php';
require_once __DIR__ . '/../models/MySQLModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/RedisModel.php';
use PhpAmqpLib\Message\AMQPMessage;

abstract class BaseWorker {
    protected $queueService;
    protected $mysqlModel;
    protected $userModel;
    protected $redisModel;
    protected $maxRetries = 3;
    protected $shouldStop = false;
    protected $processedCount = 0;
    protected $lastActivity;
    
    public function __construct() {
        $this->queueService = QueueService::getInstance();
        $this->mysqlModel = MySQLModel::getInstance();
        $this->userModel = new UserModel();
        $this->redisModel = RedisModel::getInstance();
        $this->lastActivity = time();
        
        // 注册信号处理器，用于优雅关闭
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGUSR1, [$this, 'handleSignal']);
        }
    }
    
    /**
     * 抽象方法：启动Worker
     */
    abstract public function start();
    
    /**
     * 抽象方法：处理消息
     */
    abstract public function processMessage(AMQPMessage $msg);
    
    /**
     * 处理消息错误
     */
    protected function handleMessageError(AMQPMessage $msg, $data) {
        $retryCount = $data['retry_count'] ?? 0;
        
        if ($retryCount < $this->maxRetries) {
            // 增加重试次数并重新发布消息
            $data['retry_count'] = $retryCount + 1;
            $newMessage = json_encode($data, JSON_UNESCAPED_UNICODE);
            
            // 延迟重试（指数退避）
            $delay = pow(2, $retryCount) * 1000; // 毫秒
            usleep($delay * 1000); // 转换为微秒
            
            // 重新发布到队列
            $queueName = $msg->delivery_info['routing_key'];
            $this->queueService->publishMessage($queueName, $data);
            
            error_log("[" . get_class($this) . "] 消息重试 {$retryCount}/{$this->maxRetries}");
        } else {
            // 超过最大重试次数，记录到死信队列或错误日志
            error_log("[" . get_class($this) . "] 消息处理失败，超过最大重试次数: " . json_encode($data));
        }
        
        // 确认消息（避免重复处理）
        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
    }
    
    /**
     * 验证消息格式
     */
    protected function validateMessage($data, $requiredFields = []) {
        if (!$data) {
            throw new Exception("无效的消息格式");
        }
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("缺少必需字段: {$field}");
            }
        }
        
        return true;
    }
    
    /**
     * 记录处理日志
     */
    protected function logProcess($message, $data = []) {
        $className = get_class($this);
        $logMessage = "[{$className}] {$message}";
        if (!empty($data)) {
            $logMessage .= " - Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        error_log($logMessage);
    }
    
    /**
     * 信号处理器
     */
    public function handleSignal($signal) {
        switch ($signal) {
            case SIGTERM:
            case SIGINT:
                $this->shouldStop = true;
                $this->logProcess("收到停止信号 {$signal}，准备优雅关闭");
                break;
            case SIGUSR1:
                // 用于状态检查或重新加载配置
                $this->logProcess("收到用户信号 {$signal}，当前状态: " . json_encode($this->getHealthStatus()));
                break;
        }
    }
    
    /**
     * 检查是否应该停止
     */
    protected function shouldStop() {
        // 处理信号
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        return $this->shouldStop;
    }
    
    /**
     * 获取健康状态
     */
    public function getHealthStatus() {
        return [
            'status' => $this->shouldStop ? 'stopping' : 'running',
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'processed_messages' => $this->processedCount,
            'last_activity' => $this->lastActivity,
            'uptime' => time() - $this->lastActivity
        ];
    }
    
    /**
     * 更新活动时间和计数
     */
    protected function updateActivity() {
        $this->lastActivity = time();
        $this->processedCount++;
    }
    
    /**
     * 确认消息处理成功
     */
    protected function acknowledgeMessage(AMQPMessage $msg, $message = "处理成功") {
        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
        $this->logProcess($message);
    }
}