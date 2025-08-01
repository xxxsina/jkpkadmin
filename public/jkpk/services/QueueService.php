<?php
/**
 * RabbitMQ队列服务类
 * 提供统一的消息队列操作接口
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

require_once __DIR__ . '/../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;

class QueueService {
    private static $instance = null;
    private $connection = null;
    private $channel = null;
    private $config = [];
    
    // 队列名称常量
    const QUEUE_USER_OPERATIONS = 'jkpk_user_operations_queue';
    const QUEUE_USER_LOG = 'jkpk_user_log_queue';
    const QUEUE_CHECKIN = 'jkpk_checkin_queue';
    const QUEUE_QIANDAO_LOG = 'jkpk_qiandao_log_queue';
    const QUEUE_CUSTOMER_MESSAGE = 'jkpk_customer_message_queue';

    /**
     * 私有构造函数，实现单例模式
     */
    private function __construct() {
        $this->config = require __DIR__ . '/../config/database.php';
        $this->connect();
        $this->declareQueues();
    }
    
    /**
     * 获取单例实例
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 建立RabbitMQ连接
     */
    private function connect() {
        try {
            $config = $this->config['rabbitmq'];
            $this->connection = new AMQPStreamConnection(
                $config['host'],
                $config['port'],
                $config['username'],
                $config['password'],
                $config['vhost']
            );
            $this->channel = $this->connection->channel();
            
            error_log("[QueueService] RabbitMQ连接成功");
        } catch (Exception $e) {
            error_log("[QueueService] RabbitMQ连接失败: " . $e->getMessage());
            throw new Exception("RabbitMQ连接失败");
        }
    }
    
    /**
     * 声明所有队列
     */
    private function declareQueues() {
        $queues = [
            self::QUEUE_USER_OPERATIONS,
            self::QUEUE_USER_LOG,
            self::QUEUE_CHECKIN,
            self::QUEUE_QIANDAO_LOG,
            self::QUEUE_CUSTOMER_MESSAGE,
        ];
        
        foreach ($queues as $queue) {
            $this->channel->queue_declare(
                $queue,     // queue name
                false,      // passive
                true,       // durable
                false,      // exclusive
                false       // auto_delete
            );
        }
    }
    
    /**
     * 发布用户操作消息
     */
    public function publishUserOperation($operation, $table, $data, $userId = null) {
        $message = [
            'operation' => $operation,  // insert, update, delete
            'table' => $table,
            'data' => $data,
            'user_id' => $userId,
            'timestamp' => time(),
            'retry_count' => 0
        ];
        
        return $this->publishMessage(self::QUEUE_USER_OPERATIONS, $message);
    }
    
    /**
     * user log message
     */
    public function publishUserLogQueue($data, $userId) {
        $message = [
            'operation' => 'insert',
            'table' => 'user_log',
            'data' => $data,
            'user_id' => $userId,
            'timestamp' => time(),
            'retry_count' => 0
        ];
        
        return $this->publishMessage(self::QUEUE_USER_LOG, $message);
    }
    
    /**
     * 发布加积分消息
     */
    public function publishUserScoreLog($data, $userId) {
        $message = [
            'operation' => 'insert',
            'table' => 'user_score_log',
            'data' => $data,
            'user_id' => $userId,
            'timestamp' => time(),
            'retry_count' => 0
        ];
        
        return $this->publishMessage(self::QUEUE_CHECKIN, $message);
    }

    /**
     * 发布签到日志消息
     */
    public function publishQiandaoLog($data, $userId) {
        $message = [
            'operation' => 'insert',
            'table' => 'qiandao_log',
            'data' => $data,
            'user_id' => $userId,
            'timestamp' => time(),
            'retry_count' => 0
        ];
        
        return $this->publishMessage(self::QUEUE_QIANDAO_LOG, $message);
    }

    /**
     * 发布用户提问消息
     */
    public function publishCustomerMessage($data, $userId, $operation = 'insert') {
        $message = [
            'operation' => $operation,
            'table' => 'customer_message',
            'data' => $data,
            'user_id' => $userId,
            'timestamp' => time(),
            'retry_count' => 0
        ];

        return $this->publishMessage(self::QUEUE_CUSTOMER_MESSAGE, $message);
    }
    
    /**
     * 通用消息发布方法
     */
    public function publishMessage($queueName, $messageData) {
        try {
            $messageBody = json_encode($messageData, JSON_UNESCAPED_UNICODE);
            $message = new AMQPMessage($messageBody, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
            ]);
            
            $this->channel->basic_publish($message, '', $queueName);
            
            error_log("[QueueService] 消息发布成功到队列: {$queueName}");
            return true;
        } catch (Exception $e) {
            error_log("[QueueService] 消息发布失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 消费用户操作队列消息
     * 专门用于处理用户相关操作的队列消费
     */
    public function consumeUserOperations($callback) {
        return $this->consumeMessages(self::QUEUE_USER_OPERATIONS, $callback);
    }
    
    /**
     * 消费消息（用于消费者脚本）
     */
    public function consumeMessages($queueName, $callback) {
        try {
            $this->channel->basic_qos(null, 1, null);
            $this->channel->basic_consume(
                $queueName,
                '',
                false,
                false,
                false,
                false,
                $callback
            );
            
            error_log("[QueueService] 开始消费队列: {$queueName}");
            
            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }
        } catch (Exception $e) {
            error_log("[QueueService] 消费消息失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 获取队列消息数量
     */
    public function getQueueMessageCount($queueName) {
        try {
            list($queue, $messageCount, $consumerCount) = $this->channel->queue_declare(
                $queueName,
                true  // passive
            );
            return $messageCount;
        } catch (Exception $e) {
            error_log("[QueueService] 获取队列消息数量失败: " . $e->getMessage());
            return -1;
        }
    }
    
    /**
     * 关闭连接
     */
    public function close() {
        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
    }
    
    /**
     * 析构函数
     */
    public function __destruct() {
        $this->close();
    }
}