<?php
/**
 * 提问Worker类
 * 专门处理提问相关的操作消息
 *
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

require_once __DIR__ . '/BaseWorker.php';
require_once __DIR__ . '/../models/CustomerMessageModel.php';
use PhpAmqpLib\Message\AMQPMessage;

class CustomerMessageWorker extends BaseWorker {
    private $customerMessageModel;

    public function __construct() {
        parent::__construct();
        $this->customerMessageModel = new CustomerMessageModel();
    }

    /**
     * 启动提问消费者
     */
    public function start() {
        echo "启动提问消费者\n";

        $callback = function(AMQPMessage $msg) {
            // 检查是否应该停止
            if ($this->shouldStop()) {
                echo "收到停止信号，正在优雅关闭提问消费者\n";
                return false; // 停止消费
            }

            $this->processMessage($msg);
        };

        $this->queueService->consumeMessages(QueueService::QUEUE_CUSTOMER_MESSAGE, $callback);
    }

    /**
     * 处理提问消息
     */
    public function processMessage(AMQPMessage $msg) {
        try {
            $data = json_decode($msg->body, true);

            $this->validateMessage($data, ['operation', 'data', 'user_id']);

            $operation = $data['operation'];
            $customerMessageData = $data['data'];
            $userId = $data['user_id'];

            $this->logProcess("处理提问数据: {$operation} - User: {$userId}");

            switch ($operation) {
                case 'insert':
                    $this->insertCustomerMessage($customerMessageData);
                    break;
                case 'update':
                    $this->updateCustomerMessage($customerMessageData, $userId);
                    break;
                default:
                    throw new Exception("未知的操作类型: {$operation}");
            }

            $this->acknowledgeMessage($msg, "提问数据处理成功");

        } catch (Exception $e) {
            $this->logProcess("处理提问数据失败: " . $e->getMessage());
            $this->handleMessageError($msg, $data ?? []);
        }
    }

    /**
     * 插入提问数据
     * 在insertCustomerMessage方法中添加事务处理
     */
    private function insertCustomerMessage($customerMessageData) {
        $this->mysqlModel->beginTransaction();
        try {
            $customerMessageId = $this->customerMessageModel->createCustomerMessage($customerMessageData);
            if ($customerMessageId) {
                $this->mysqlModel->commit(); // 添加这行
                $this->logProcess("提问数据创建成功，ID: {$customerMessageId}", $customerMessageData);
            } else {
                throw new Exception("提问数据创建失败");
            }
        } catch (Exception $e) {
            $this->logProcess("提问数据创建 Exceptio：", $e->getMessage());
            $this->mysqlModel->rollback();
            throw $e;
        }
    }

    /**
     * 更新提问数据
     */
    private function updateCustomerMessage($customerMessageData, $userId) {
        if (isset($customerMessageData['id'])) {
            $customerMessageId = $customerMessageData['id'];
            unset($customerMessageData['id']);

            $result = $this->customerMessageModel->updateCustomerMessage($customerMessageId, $customerMessageData);

            if ($result) {
                $this->logProcess("提问变动更新成功，ID: {$customerMessageId}");
            } else {
                throw new Exception("提问变动更新失败");
            }
        } else {
            throw new Exception("更新操作缺少记录ID");
        }
    }
}

// 如果直接运行此脚本，启动消费者
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $worker = new CustomerMessageWorker();
    $worker->start();
}