<?php
/**
 * 数据同步消费者类（已重构）
 * 此类现在主要用于向后兼容，实际功能已拆分到各个专门的Worker类中
 * 
 * @author 健康派卡开发团队
 * @version 2.0
 * @date 2024-01-01
 * @deprecated 建议使用 WorkerManager 和各个专门的 Worker 类
 */

require_once __DIR__ . '/WorkerManager.php';
require_once __DIR__ . '/../services/QueueService.php';
require_once __DIR__ . '/../models/MySQLModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/RedisModel.php';
use PhpAmqpLib\Message\AMQPMessage;

class DataSyncWorker {
    private $workerManager;
    private $queueService;
    private $mysqlModel;
    private $userModel;
    private $redisModel;
    private $maxRetries = 3;
    
    public function __construct() {
        $this->workerManager = new WorkerManager();
        $this->queueService = QueueService::getInstance();
        $this->mysqlModel = MySQLModel::getInstance();
        $this->userModel = new UserModel();
        $this->redisModel = RedisModel::getInstance();
    }
    
    /**
     * 启动所有消费者（向后兼容方法）
     * @deprecated 建议直接使用 WorkerManager::startAllWorkers()
     */
    public function startAllWorkers() {
        echo "[DataSyncWorker] 使用新的WorkerManager启动所有消费者...\n";
        $this->workerManager->startAllWorkers();
    }
    
    /**
     * 启动用户操作消费者（向后兼容方法）
     * @deprecated 建议使用 UserOperationsWorker
     */
    public function startUserOperationsWorker() {
        $this->workerManager->startSpecificWorker('user_operations');
    }
    
    /**
     * 启动运动数据消费者（向后兼容方法）
     * @deprecated 建议使用 SportDataWorker
     */
    public function startSportDataWorker() {
        $this->workerManager->startSpecificWorker('sport_data');
    }
    
    /**
     * 启动签到消费者（向后兼容方法）
     * @deprecated 建议使用 CheckinWorker
     */
    public function startCheckinWorker() {
        $this->workerManager->startSpecificWorker('checkin');
    }
    
    /**
     * 启动登录日志消费者（向后兼容方法）
     * @deprecated 建议使用 LoginLogWorker
     */
    public function startLoginLogWorker() {
        $this->workerManager->startSpecificWorker('login_log');
    }
    

    
    /**
     * 获取数据库连接（公共方法）
     */
    public function getMySQLModel() {
        return $this->mysqlModel;
    }
    
    /**
     * 获取Redis连接（公共方法）
     */
    public function getRedisModel() {
        return $this->redisModel;
    }
    
    /**
     * 获取队列服务（公共方法）
     */
    public function getQueueService() {
        return $this->queueService;
    }
    
    /**
     * 获取用户模型（公共方法）
     */
    public function getUserModel() {
        return $this->userModel;
    }
    

    
    /**
     * 处理消息错误（公共方法）
     */
    public function handleMessageError(AMQPMessage $msg, $data = []) {
        $retryCount = 0;
        
        // 获取消息头中的重试次数
        $headers = $msg->get_properties();
        if (isset($headers['application_headers']) && isset($headers['application_headers']['x-retry-count'])) {
            $retryCount = $headers['application_headers']['x-retry-count'];
        }
        
        if ($retryCount < $this->maxRetries) {
            // 增加重试次数并重新发送
            $retryCount++;
            $newHeaders = ['x-retry-count' => $retryCount];
            
            // 延迟重试（指数退避）
            $delay = pow(2, $retryCount) * 1000; // 毫秒
            
            echo "[DataSyncWorker] 消息处理失败，将在 {$delay}ms 后重试第 {$retryCount} 次\n";
            
            // 这里可以发送到延迟队列或者直接重新发送
            // $this->queueService->publishMessage($msg->getRoutingKey(), $msg->body, $newHeaders);
        } else {
            echo "[DataSyncWorker] 消息重试次数超限，记录到死信队列\n";
            // 可以发送到死信队列进行人工处理
        }
        
        // 确认消息
        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
    }
    
    /**
     * 验证消息格式（公共方法）
     */
    public function validateMessage($data, $requiredFields = []) {
        if (!$data) {
            throw new Exception('消息数据为空');
        }
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("缺少必需字段: {$field}");
            }
        }
        
        return true;
    }
    
    /**
     * 记录处理日志（公共方法）
     */
    public function logProcess($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] [{$level}] {$message}\n";
        
        // 可以扩展为写入日志文件
        error_log("[DataSyncWorker] [{$level}] {$message}");
    }
}

// 如果直接运行此脚本，启动消费者
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $worker = new DataSyncWorker();
    $worker->startAllWorkers();
}