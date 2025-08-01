<?php
/**
 * Worker管理器
 * 负责启动和管理所有的Worker进程
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

require_once __DIR__ . '/UserOperationsWorker.php';
require_once __DIR__ . '/UserLogWorker.php';
require_once __DIR__ . '/CheckinWorker.php';
require_once __DIR__ . '/QiandaoLogWorker.php';
require_once __DIR__ . '/LoginLogWorker.php';
require_once __DIR__ . '/CustomerMessageWorker.php';

class WorkerManager {
    private $workers = [];
    private $pids = [];
    
    public function __construct() {
        // 注册所有Worker类
        $this->workers = [
            'jkpk_user_operations' => UserOperationsWorker::class,
            'jkpk_user_log' => UserLogWorker::class,
            'jkpk_checkin' => CheckinWorker::class,
            'jkpk_qiandao_log' => QiandaoLogWorker::class,
            'jkpk_login_log' => LoginLogWorker::class,
            'jkpk_customer_message' => CustomerMessageWorker::class,
        ];
    }
    
    /**
     * 启动所有Worker进程
     * 注意：使用Supervisor时不应该调用此方法
     */
    public function startAllWorkers() {
        echo "错误：使用Supervisor管理时，请不要启动所有Worker\n";
        echo "请为每个Worker配置单独的Supervisor程序\n";
        exit(1);
    }
    
    /**
     * 启动单个Worker
     * 移除pcntl_fork，直接运行Worker（适用于Supervisor管理）
     */
    public function startWorker($name, $workerClass) {
        echo "启动 {$name} Worker...\n";
        
        try {
            $worker = new $workerClass();
            $worker->start();
        } catch (Exception $e) {
            error_log("[WorkerManager] {$name} Worker启动失败: " . $e->getMessage());
            exit(1);
        }
    }
    
    /**
     * 启动指定的Worker
     */
    public function startSpecificWorker($workerName) {
        if (!isset($this->workers[$workerName])) {
            throw new Exception("未知的Worker类型: {$workerName}");
        }
        
        $workerClass = $this->workers[$workerName];
        $this->startWorker($workerName, $workerClass);
        $this->waitForWorker($workerName);
    }
    
    /**
     * 获取可用的Worker列表
     */
    public function getAvailableWorkers() {
        return array_keys($this->workers);
    }
    
    /**
     * 检查Worker类型是否有效
     */
    public function isValidWorker($workerName) {
        return isset($this->workers[$workerName]);
    }
    
    /**
     * 停止所有Worker进程（已废弃，使用Supervisor管理）
     */
    public function stopAllWorkers() {
        echo "错误：使用Supervisor管理时，请使用supervisorctl命令停止Worker\n";
        echo "示例：supervisorctl stop shenhuobao:*\n";
        exit(1);
    }
    
    /**
     * 获取运行中的Worker状态（已废弃，使用Supervisor管理）
     */
    public function getWorkerStatus() {
        echo "错误：使用Supervisor管理时，请使用supervisorctl status命令查看状态\n";
        echo "示例：supervisorctl status shenhuobao:*\n";
        exit(1);
    }
}

// 如果直接运行此脚本，启动所有Worker
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $manager = new WorkerManager();
    
    // 处理命令行参数
    if ($argc > 1) {
        $workerName = $argv[1];
        try {
            echo "启动指定Worker: {$workerName}\n";
            $manager->startSpecificWorker($workerName);
        } catch (Exception $e) {
            echo "错误: " . $e->getMessage() . "\n";
            echo "可用的Worker类型: " . implode(', ', $manager->getAvailableWorkers()) . "\n";
            exit(1);
        }
    } else {
        // 注册信号处理器
        pcntl_signal(SIGTERM, function() use ($manager) {
            $manager->stopAllWorkers();
            exit(0);
        });
        
        pcntl_signal(SIGINT, function() use ($manager) {
            $manager->stopAllWorkers();
            exit(0);
        });
        
        $manager->startAllWorkers();
    }
}