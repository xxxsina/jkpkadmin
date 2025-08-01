<?php
/**
 * 用户日志Worker类
 * 专门处理用户日志的插入操作
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

require_once __DIR__ . '/BaseWorker.php';
require_once __DIR__ . '/../db/UserLogTable.php';
require_once __DIR__ . '/../models/UserLogModel.php';
use PhpAmqpLib\Message\AMQPMessage;

class UserLogWorker extends BaseWorker {
    
    protected $userLogModel;
    
    public function __construct() {
        parent::__construct();
        $this->userLogModel = new UserLogModel();
    }
    
    /**
     * 启动用户日志消费者
     */
    public function start() {
        echo "启动用户日志消费者\n";
        
        $callback = function(AMQPMessage $msg) {
            $this->processMessage($msg);
        };
        
        $this->queueService->consumeMessages(QueueService::QUEUE_USER_LOG, $callback);
    }
    
    /**
     * 处理用户日志消息
     */
    public function processMessage(AMQPMessage $msg) {
        try {
            $data = json_decode($msg->body, true);
            
            $this->validateMessage($data, ['operation', 'data']);
            
            $operation = $data['operation'];
            $logData = $data['data'];
            $userId = $logData['user_id'] ?? null;
            
            $this->logProcess("处理用户日志: {$operation} - User: {$userId}");
            
            // 用户日志只处理插入操作
            if ($operation === 'insert') {
                $this->insertUserLog($logData);
            } else {
                throw new Exception("用户日志只支持插入操作，当前操作: {$operation}");
            }
            
            $this->acknowledgeMessage($msg, "用户日志处理成功");
            
        } catch (Exception $e) {
            $this->logProcess("处理用户日志失败: " . $e->getMessage());
            $this->handleMessageError($msg, $data ?? []);
        }
    }
    
    /**
     * 插入用户日志数据到MySQL
     */
    private function insertUserLog($logData) {
        // 使用UserLogTable填充默认值
        $logData = UserLogTable::fillDefaults($logData);
        // 插入日志记录
        $result = $this->userLogModel->createUserLog($logData);
        
        if (!$result) {
            throw new Exception("用户日志插入失败");
        }
        
        $this->logProcess("用户日志插入成功", [
            'user_id' => $logData['user_id'],
            'title' => $logData['title'],
            'url' => $logData['url']
        ]);
    }
}

// 如果直接运行此脚本，启动消费者
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $worker = new UserLogWorker();
    $worker->start();
}
?>