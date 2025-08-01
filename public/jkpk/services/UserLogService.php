<?php
/**
 * 用户服务类
 * 封装用户相关的业务逻辑，包括注册、登录、用户信息管理等
 */

require_once __DIR__ . '/QueueService.php';

class UserLogService {
    private static $instance = null;
    private $queueService;
    public function __construct() {
        $this->queueService = QueueService::getInstance();
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
     * 发送用户操作日志数据到队列进行异步处理
     * 实现高复用性的队列集成
     * @param $userData // 用户数据
     * @return bool // 是否发送成功
     * @author LEE
     * @Date 2025-06-23 21:03
     */
    public function publishUserLogToQueue($userData) {
        try {
            // 设置title地址（如果未提供）
            if (empty($userData['title'])) {
                $userData['title'] = '未知';
            }

            // 设置url地址（如果未提供）
            if (empty($userData['url'])) {
                $userData['url'] = $_SERVER['REQUEST_URI'] ?? '';
            }

            // 设置IP地址（如果未提供）
            if (empty($userData['ip'])) {
                $userData['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
            }

            // 设置User-Agent（如果未提供）
            if (empty($userData['useragent'])) {
                $userData['useragent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            }

            // 发送到用户操作队列
            $result = $this->queueService->publishUserLogQueue(
                $userData,         // 用户数据
                $userData['user_id']     // 用户ID
            );

            if ($result) {
                error_log("[UserService] 用户操作日志数据已发送到队列: 用户ID {$userData['id']}");
            } else {
                error_log("[UserService] 用户操作日志数据发送到队列失败: 用户ID {$userData['id']}");
            }

            return $result;

        } catch (Exception $e) {
            error_log("[UserService] 发送用户操作日志数据到队列异常: " . $e->getMessage());
            // 队列发送失败不影响注册流程，只记录日志
            return false;
        }
    }
}