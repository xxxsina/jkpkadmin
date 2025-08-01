<?php /** @noinspection ALL */
/**
 * 用户增加积分API接口
 * 处理用户积分增加功能
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

// 引入依赖
require_once __DIR__ . '/../utils/ApiUtils.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../services/QueueService.php';
require_once __DIR__ . '/../models/RedisModel.php';
require_once __DIR__ . '/../models/QueueLogModel.php';

// 处理CORS
ApiUtils::handleCors();

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiUtils::error('只支持POST请求', 405);
}

/**
 * 定义增加积分参数验证规则
 */
function getAddScoreValidationRules() {
    return [
        'user_id' => 'required|integer',
        'type' => 'required|string'
    ];
}

/**
 * 处理增加积分请求
 */
function handleAddScore() {
    try {
        // 获取请求参数
        $params = ApiUtils::getRequestParams(['user_id', 'type']);

        // 从header中获取token
        $token = ApiUtils::getTokenFromHeader();
        if (empty($token)) {
            ApiUtils::unauthorized('缺少访问令牌，请在请求头中提供token');
        }

        // 验证参数
        $rules = getAddScoreValidationRules();
        $errors = ApiUtils::validateParams($params, $rules);
        
        if (!empty($errors)) {
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                $errorMessages[] = implode(', ', $fieldErrors);
            }
            ApiUtils::error('参数验证失败: ' . implode('; ', $errorMessages), 400);
        }
        
        $userId = $params['user_id'];
        $type = $params['type'];

        // 验证token
        $userService = new UserService();
        if (!$userService->validateUserToken($userId, $token)) {
            ApiUtils::error('登录失效，请退出后重新登录', 401);
        }
        
        // 获取配置
        $config = require __DIR__ . '/../config/config.php';
        
        // 根据type获取对应的积分值
        $scoreToAdd = 0;
        $memo = '';

        // 获取用户详细信息
        $redisModel = RedisModel::getInstance();
        // 获取当日加分缓存
        $cacheKey = "user_add_score:{$userId}:" . date('Y-m-d');
        $addScoreRedisData = $redisModel->get($cacheKey);
        $todayAddScoreCount = $addScoreRedisData['add_count'] ?? 0;

        switch ($type) {
            case 'score_again_more':
                $maxScoreAgainMore = $config['checkin_config']['max_score_again_more'] ?? 10;
                if ($todayAddScoreCount >= $maxScoreAgainMore) {
                    ApiUtils::error("今日赚取次数已达上限({$maxScoreAgainMore}次)", 400);
                }
                $scoreToAdd = $config['checkin_config']['score_again_more'] ?? 10;
                $memo = '赚取更多积分奖励';
                break;
            default:
                ApiUtils::error('目前暂不支持加分: ' . $type, 400);
        }

        if ($scoreToAdd <= 0) {
            ApiUtils::error('积分配置错误，积分值必须大于0', 500);
        }
        // 验证短时提交
        $uniqueRandom = $redisModel->verifyUniqueRandom('score', $userId);
        if ($uniqueRandom === false) {
            ApiUtils::error("不能频繁提交", 400);
        }

        $user = $redisModel->hGetAll("user:{$userId}");
        
        $currentScore = $user['score'] ?? 0;
        $newScore = $currentScore + $scoreToAdd;

        // 更新Redis中的用户信息
        $updateData = [
            'score' => $newScore,
            'updatetime' => time(),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $redisModel->hMSet("user:{$userId}", $updateData);
        // 记录到Redis缓存
        $add_count = $todayAddScoreCount + 1;
        $redisModel->set($cacheKey, json_encode([
            'add_count' => $add_count,
            'add_score_singe' => $scoreToAdd,
            'score_today_total' => $scoreToAdd * $add_count,
            'last_add_time' => time()
        ]), 86400); // 缓存24小时
        // 准备积分变动数据
        $scoreLogData = [
            'user_id' => $userId,
            'type' => 'add_score',
            'numb' => $add_count,
            'score' => $scoreToAdd,
            'before' => $currentScore,
            'after' => $newScore,
            'memo' => $memo,
            'year' => (int)date('Y'),
            'month' => (int)date('n'),
            'day' => (int)date('j'),
            'unique_random' => $uniqueRandom,
            'createtime' => time()
        ];
        
        // 发布到加积分队列
        $queueService = QueueService::getInstance();
        $queueResult = $queueService->publishUserScoreLog($scoreLogData, $userId);
        
        if (!$queueResult) {
            // 入列失败后，将数据写入队列日志表
            try {
                $queueLogModel = new QueueLogModel();
                $queueLogData = [
                    'user_id' => $userId,
                    'type' => $type,
                    'status' => '0', // 未处理状态
                    'content' => json_encode($scoreLogData, JSON_UNESCAPED_UNICODE)
                ];
                $queueLogModel->createQueueLog($queueLogData);
                
                // 记录日志
                error_log("Queue failed, saved to queue_log table for user: {$userId}");
            } catch (Exception $logException) {
                error_log("Failed to save queue log: " . $logException->getMessage());
            }
            ApiUtils::error('积分处理失败，请稍后重试', 500);
        }

        // 返回成功结果
        $result = [
            'user_id' => $userId,
            'type' => $type,
            'score_added' => $scoreToAdd,    // 本次增加的积分
//            'current_score' => $currentScore, // 增加前的积分
            'new_score' => $newScore,        // 增加后的总积分
            'memo' => $memo,                 // 积分说明
            'add_time' => date('Y-m-d H:i:s') // 增加时间
        ];
        
        // 记录成功日志并返回结果
        ApiUtils::logApiAccess('add_score', $params, $result);
        ApiUtils::success('积分增加成功', $result);
        
    } catch (Exception $e) {
        // 记录系统错误日志
        ApiUtils::logApiAccess('add_score', $params ?? [], null, $e->getMessage());
        ApiUtils::error('系统错误，请稍后重试: ' . $e->getMessage(), 500);
    }
}

// 执行增加积分处理
handleAddScore();

/**
 * API使用说明
 * 
 * 接口地址: /api/add_score.php
 * 请求方式: POST
 * 请求格式: JSON
 * 
 * 请求头:
 * - Authorization: Bearer {token} 或 Token: {token}
 * 
 * 请求参数:
 * - user_id (integer, 必填): 用户ID
 * - type (string, 必填): 积分类型，支持值：score_again_more
 * 
 * 请求示例:
 * POST /api/add_score.php
 * Headers:
 *   Authorization: Bearer abc123token
 *   Content-Type: application/json
 * 
 * Body:
 * {
 *     "user_id": 123,
 *     "type": "score_again_more"
 * }
 * 
 * 响应格式:
 * {
 *     "code": 200,
 *     "message": "积分增加成功",
 *     "data": {
 *         "user_id": 123,
 *         "type": "score_again_more",
 *         "score_added": 10, // 本次增加的积分
 *         "new_score": 110, // 增加后的总积分
 *         "memo": "赚取更多积分", // 积分说明
 *         "add_time": "2024-01-01 12:00:00" // 增加时间
 *     }
 * }
 * 
 * 错误响应:
 * {
 *     "code": 400|401|500,
 *     "message": "错误信息",
 *     "timestamp": 1750845894,
 *     "datetime": "2025-06-25 18:04:54"
 * }
 *
 * 错误码说明:
 * - 400: 参数错误或不支持的积分类型
 * - 401: token验证失败
 * - 404: 用户不存在
 * - 405: 请求方法错误
 * - 500: 系统错误或积分配置错误
 * 
 * 积分类型说明:
 * - score_again_more: 赚取更多积分，对应配置文件中的 checkin_config.score_again_more
 * 
 * 配置说明:
 * 在 config/config.php 中的 checkin_config 配置:
 * 'checkin_config' => [
 *     'score_again_more' => 10,   // 赚取更多积分单次获得的分数
 * ]
 */
?>