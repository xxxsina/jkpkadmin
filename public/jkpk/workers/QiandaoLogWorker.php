<?php
/**
 * 签到日志Worker类
 * 专门处理签到日志记录的消息消费者
 * 此消费者仅用于日志记录，不包含复杂的业务逻辑判断
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

require_once __DIR__ . '/BaseWorker.php';
require_once __DIR__ . '/../models/QiandaoLogModel.php';
use PhpAmqpLib\Message\AMQPMessage;

class QiandaoLogWorker extends BaseWorker {
    private $qiandaoLogModel;
    
    public function __construct() {
        parent::__construct();
        $this->qiandaoLogModel = new QiandaoLogModel();
    }
    
    /**
     * 启动签到日志消费者
     */
    public function start() {
        echo "启动签到日志消费者\n";
        
        $callback = function(AMQPMessage $msg) {
            $this->processMessage($msg);
        };
        
        $this->queueService->consumeMessages(QueueService::QUEUE_QIANDAO_LOG, $callback);
    }
    
    /**
     * 处理签到日志消息
     * 简化处理逻辑，仅用于日志记录
     */
    public function processMessage(AMQPMessage $msg) {
        try {
            $data = json_decode($msg->body, true);
            
            // 基本验证
            $this->validateMessage($data, ['user_id']);
            
            $this->logProcess("处理签到日志数据 - User: {$data['user_id']}");
            
            // 直接插入签到日志记录
            $this->insertQiandaoLog($data['data']);
            
            $this->acknowledgeMessage($msg, "签到日志记录成功");
            
        } catch (Exception $e) {
            $this->logProcess("处理签到日志失败: " . $e->getMessage());
            $this->handleMessageError($msg, $data ?? []);
        }
    }
    
    /**
     * 插入签到日志记录
     * 简化逻辑，直接记录日志
     */
    private function insertQiandaoLog($data) {
        try {
            // 准备签到日志数据
            $qiandaoLogData = [
                'user_id' => $data['user_id'],
                'device' => !empty($data['device']) ? $data['device'] : 'unknown',
                'createtime' => $data['createtime'] ?? time(),
                'updatetime' => time()
            ];
            // 创建签到日志记录
            $logId = $this->qiandaoLogModel->createQiandaoLog($qiandaoLogData);
            
            if ($logId) {
                $this->logProcess("签到日志记录创建成功，ID: {$logId}", $qiandaoLogData);
            } else {
                throw new Exception("签到日志记录创建失败");
            }
            
        } catch (Exception $e) {
            $this->logProcess("签到日志插入失败: " . $e->getMessage());
            throw $e;
        }
    }
}

// 如果直接运行此脚本，启动消费者
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $worker = new QiandaoLogWorker();
    $worker->start();
}