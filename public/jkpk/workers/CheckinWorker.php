<?php
/**
 * 签到Worker类
 * 专门处理签到相关的操作消息
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

require_once __DIR__ . '/BaseWorker.php';
require_once __DIR__ . '/../models/UserScoreLogModel.php';
require_once __DIR__ . '/../models/ScoreCalendarModel.php';
use PhpAmqpLib\Message\AMQPMessage;

class CheckinWorker extends BaseWorker {
    private $userScoreLogModel;
    private $scoreCalendarModel;

    public function __construct() {
        parent::__construct();
        $this->userScoreLogModel = new UserScoreLogModel();
        $this->scoreCalendarModel = new ScoreCalendarModel();
    }
    
    /**
     * 启动签到消费者
     */
    public function start() {
        echo "启动签到消费者\n";
        
        $callback = function(AMQPMessage $msg) {
            // 检查是否应该停止
            if ($this->shouldStop()) {
                echo "收到停止信号，正在优雅关闭签到消费者\n";
                return false; // 停止消费
            }
            
            $this->processMessage($msg);
            $this->updateActivity(); // 更新活动时间和计数
        };
        
        $this->queueService->consumeMessages(QueueService::QUEUE_CHECKIN, $callback);
    }
    
    /**
     * 处理签到消息
     */
    public function processMessage(AMQPMessage $msg) {
        try {
            $data = json_decode($msg->body, true);
            
            $this->validateMessage($data, ['operation', 'data', 'user_id']);
            
            $operation = $data['operation'];
            $checkinData = $data['data'];
            $userId = $data['user_id'];
            
            $this->logProcess("处理签到数据: {$operation} - User: {$userId}");
            
            switch ($operation) {
                case 'insert':
                    $this->insertCheckin($checkinData);
                    break;
                case 'update':
                    $this->updateCheckin($checkinData, $userId);
                    break;
                default:
                    throw new Exception("未知的操作类型: {$operation}");
            }
            
            $this->acknowledgeMessage($msg, "签到数据处理成功");
            
        } catch (Exception $e) {
            $this->logProcess("处理签到数据失败: " . $e->getMessage());
            $this->handleMessageError($msg, $data ?? []);
        }
    }
    
    /**
     * 插入签到积分变动数据
     * 在insertCheckin方法中添加事务处理
     */
    private function insertCheckin($checkinData) {
        $this->mysqlModel->beginTransaction();
        try {
            $scoreLogId = $this->userScoreLogModel->createScoreLog($checkinData);
            if ($scoreLogId) {
                $this->updateUserScore($checkinData['user_id'], $checkinData['after']);
                // 加入积分日历
                $this->modifyScoreCalendar($checkinData['user_id'], $checkinData);
                // 提交事务
                $this->mysqlModel->commit();
                $this->logProcess("积分变动记录创建成功，ID: {$scoreLogId}");
            } else {
                throw new Exception("积分变动记录创建失败");
            }
        } catch (Exception $e) {
            $this->mysqlModel->rollback();
            throw $e;
        }
    }
    
    /**
     * 更新签到数据
     */
    private function updateCheckin($checkinData, $userId) {
        if (isset($checkinData['id'])) {
            $scoreLogId = $checkinData['id'];
            unset($checkinData['id']);
            
            $result = $this->userScoreLogModel->updateScoreLog($scoreLogId, $checkinData);
            
            if ($result) {
                // 如果更新了积分相关字段，同步更新用户积分
                if (isset($checkinData['after'])) {
                    $this->updateUserScore($userId, $checkinData['after']);
                }
                $this->logProcess("积分变动记录更新成功，ID: {$scoreLogId}");
            } else {
                throw new Exception("积分变动记录更新失败");
            }
        } else {
            throw new Exception("更新操作缺少记录ID");
        }
    }
    
    /**
     * 更新用户积分
     */
    private function updateUserScore($userId, $newScore) {
        $userData = ['score' => $newScore, 'updatetime' => time()];
        $result = $this->userModel->updateUser($userId, $userData);
        
        if ($result) {
            $this->logProcess("用户积分更新成功，用户ID: {$userId}, 新积分: {$newScore}");
        } else {
            $this->logProcess("用户积分更新失败，用户ID: {$userId}");
        }
    }

    private function modifyScoreCalendar($userId, $data) {
        $this->scoreCalendarModel->incrementUserDateNumb(
            $userId,
            $data['type'],
            $data['year'],
            $data['month'],
            $data['day'],
            $data['numb']
        );
    }
}

// 如果直接运行此脚本，启动消费者
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $worker = new CheckinWorker();
    $worker->start();
}