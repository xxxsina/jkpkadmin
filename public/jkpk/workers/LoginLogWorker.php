<?php
/**
 * 登录日志Worker类
 * 专门处理登录日志相关的操作消息
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

require_once __DIR__ . '/BaseWorker.php';
use PhpAmqpLib\Message\AMQPMessage;

class LoginLogWorker extends BaseWorker {
    
    /**
     * 启动登录日志消费者
     */
    public function start() {
        echo "启动登录日志消费者\n";
        
        $callback = function(AMQPMessage $msg) {
            $this->processMessage($msg);
        };
        
        $this->queueService->consumeMessages(QueueService::QUEUE_LOGIN_LOG, $callback);
    }
    
    /**
     * 处理登录日志消息
     */
    public function processMessage(AMQPMessage $msg) {
        try {
            $data = json_decode($msg->body, true);
            
            $this->validateMessage($data, ['data', 'user_id']);
            
            $logData = $data['data'];
            $userId = $data['user_id'];
            
            $this->logProcess("处理登录日志: User: {$userId}");
            
            $this->insertLoginLog($logData);
            
            $this->acknowledgeMessage($msg, "登录日志处理成功");
            
        } catch (Exception $e) {
            $this->logProcess("处理登录日志失败: " . $e->getMessage());
            $this->handleMessageError($msg, $data ?? []);
        }
    }
    
    /**
     * 插入登录日志
     */
    private function insertLoginLog($logData) {
        $tableName = $this->mysqlModel->getTableName('user_login_logs');
        $sql = "INSERT INTO {$tableName} (user_id, login_ip, login_device, login_time, login_status, failure_reason)
                VALUES (:user_id, :login_ip, :login_device, :login_time, :login_status, :failure_reason)";
        
        $params = [
            ':user_id' => $logData['user_id'],
            ':login_ip' => $logData['login_ip'] ?? '',
            ':login_device' => $logData['login_device'] ?? '',
            ':login_time' => $logData['login_time'] ?? date('Y-m-d H:i:s'),
            ':login_status' => $logData['login_status'] ?? 1,
            ':failure_reason' => $logData['failure_reason'] ?? null
        ];
        
        $this->mysqlModel->query($sql, $params);
    }
}

// 如果直接运行此脚本，启动消费者
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $worker = new LoginLogWorker();
    $worker->start();
}