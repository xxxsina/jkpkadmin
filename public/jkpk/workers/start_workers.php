#!/usr/bin/env php
<?php
/**
 * Worker启动脚本
 * 用于启动各种消息队列消费者
 * 
 * 使用方法:
 * php start_workers.php                    # 启动所有Worker
 * php start_workers.php user_operations    # 只启动用户操作Worker
 * php start_workers.php checkin            # 只启动签到Worker
 * php start_workers.php login_log          # 只启动登录日志Worker
 * php start_workers.php qiandao_log        # 只启设备记录日志Worker
 *
 * @author 健康派卡开发团队
 * @version 2.0
 * @date 2024-01-01
 */

require_once __DIR__ . '/WorkerManager.php';

// 检查命令行参数
$workerType = $argv[1] ?? 'all';

try {
    $manager = new WorkerManager();
    
    echo "=== 健康派卡消息队列Worker启动器 ===\n";
    echo "启动时间: " . date('Y-m-d H:i:s') . "\n";
    echo "Worker类型: {$workerType}\n";
    echo "==============================\n\n";
    
    if ($workerType === 'all') {
        echo "错误：使用Supervisor管理时，请不要启动所有Worker\n";
        echo "请为每个Worker配置单独的Supervisor程序\n";
        echo "\n可用的Worker类型：\n";
        foreach ($manager->getAvailableWorkers() as $worker) {
            echo "  - {$worker}\n";
        }
        echo "\n示例Supervisor配置请参考项目文档\n";
        exit(1);
    } else {
        if (!$manager->isValidWorker($workerType)) {
            echo "错误：未知的Worker类型: {$workerType}\n";
            echo "可用的Worker类型：\n";
            foreach ($manager->getAvailableWorkers() as $worker) {
                echo "  - {$worker}\n";
            }
            exit(1);
        }
        echo "启动指定Worker: {$workerType}\n";
        $manager->startSpecificWorker($workerType);
    }
    
} catch (Exception $e) {
    echo "启动失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 信号处理
function signalHandler($signal) {
    echo "\n收到信号 {$signal}，正在关闭Worker...\n";
    // 这里可以添加优雅关闭逻辑
    exit(0);
}

// 注册信号处理器
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, 'signalHandler');
    pcntl_signal(SIGINT, 'signalHandler');
    pcntl_signal(SIGQUIT, 'signalHandler');
}

echo "\nWorker已启动，按 Ctrl+C 停止\n";

// 保持主进程运行
while (true) {
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }
    sleep(1);
}