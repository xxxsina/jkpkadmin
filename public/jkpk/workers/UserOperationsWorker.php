<?php
/**
 * 用户操作Worker类
 * 专门处理用户相关的操作消息
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

require_once __DIR__ . '/BaseWorker.php';
require_once __DIR__ . '/../db/UserTable.php';
require_once __DIR__ . '/../models/UserModel.php';
use PhpAmqpLib\Message\AMQPMessage;

class UserOperationsWorker extends BaseWorker {
    
    protected $userModel;
    
    public function __construct() {
        parent::__construct();
        $this->userModel = new UserModel();
    }
    
    /**
     * 启动用户操作消费者
     */
    public function start() {
        echo "启动用户操作消费者\n";
        
        $callback = function(AMQPMessage $msg) {
            $this->processMessage($msg);
        };
        
        $this->queueService->consumeMessages(QueueService::QUEUE_USER_OPERATIONS, $callback);
    }
    
    /**
     * 处理用户操作消息
     */
    public function processMessage(AMQPMessage $msg) {
        try {
            $data = json_decode($msg->body, true);
            
            $this->validateMessage($data, ['operation', 'table', 'data']);
            
            $operation = $data['operation'];
            $table = $data['table'];
            $userData = $data['data'];
            $userId = $data['user_id'] ?? null;
            
            $this->logProcess("处理用户操作: {$operation} - {$table} - User: {$userId}");
            
            switch ($operation) {
                case 'insert':
                    $this->insertUser($userData);
                    break;
                case 'update':
                    $this->updateUser($userData, $userId);
                    break;
                case 'delete':
                    $this->deleteUser($userId);
                    break;
                default:
                    throw new Exception("未知的操作类型: {$operation}");
            }
            
            $this->acknowledgeMessage($msg, "用户操作处理成功");
            
        } catch (Exception $e) {
            $this->logProcess("处理用户操作失败: " . $e->getMessage());
            $this->handleMessageError($msg, $data ?? []);
        }
    }
    
    /**
     * 插入用户数据到MySQL
     */
    private function insertUser($userData) {
        // 使用UserTable填充默认值
        $userData = UserTable::fillDefaults($userData);
        
        // 检查用户是否已存在
        if (isset($userData['id']) && $this->userModel->getUserById($userData['id'])) {
            // 用户已存在，执行更新操作
            $userId = $userData['id'];
            unset($userData['id']); // 移除ID字段，避免更新主键
            unset($userData['createtime']); // 移除createtime字段，避免更新主键
            $this->userModel->updateUser($userId, $userData);
        } else {
            // 用户不存在，创建新用户
            $this->userModel->createUser($userData);
        }
        

    }
    
    /**
     * 更新用户数据到MySQL
     */
    private function updateUser($userData, $userId) {
        // 添加更新时间
        $userData['updatetime'] = time();
        
        // 过滤只允许更新的字段
        $allowedFields = UserTable::getUpdatableFields();
        $filteredData = [];
        
        foreach ($userData as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $filteredData[$field] = $value;
            }
        }
        
        if (!empty($filteredData)) {
            $this->userModel->updateUser($userId, $filteredData);
        }
    }
    
    /**
     * 删除用户数据（软删除）
     */
    private function deleteUser($userId) {
        $updateData = [
            'status' => 'deleted',
            'updatetime' => time()
        ];
        $this->userModel->updateUser($userId, $updateData);
    }
}

// 如果直接运行此脚本，启动消费者
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $worker = new UserOperationsWorker();
    $worker->start();
}