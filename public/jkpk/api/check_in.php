<?php /** @noinspection ALL */
/**
 * 用户签到API接口
 * 处理用户每日签到功能
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

// 引入依赖
require_once __DIR__ . '/../utils/ApiUtils.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../services/QueueService.php';
require_once __DIR__ . '/../services/CalendarService.php';
require_once __DIR__ . '/../models/RedisModel.php';
require_once __DIR__ . '/../models/QueueLogModel.php';
require_once __DIR__ . '/../config/common.php';

// 处理CORS
ApiUtils::handleCors();

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiUtils::error('只支持POST请求', 405);
}

/**
 * 定义签到参数验证规则
 */
function getCheckinValidationRules() {
    return [
        'user_id' => 'required|integer'
    ];
}

/**
 * 处理签到请求
 */
function handleCheckin() {
    try {
        // 获取请求参数
        $params = ApiUtils::getRequestParams(['user_id']);

        // 从header中获取token
        $token = ApiUtils::getTokenFromHeader();
        if (empty($token)) {
            ApiUtils::unauthorized('缺少访问令牌，请在请求头中提供token');
        }

        // 验证参数
        $rules = getCheckinValidationRules();
        $errors = ApiUtils::validateParams($params, $rules);
        
        if (!empty($errors)) {
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                $errorMessages[] = implode(', ', $fieldErrors);
            }
            ApiUtils::error('参数验证失败: ' . implode('; ', $errorMessages), 400);
        }
        
        $userId = $params['user_id'];

        // 验证token
        $userService = new UserService();
        if (!$userService->validateUserToken($userId, $token)) {
            ApiUtils::error('登录失效，请退出后重新登录', 401);
        }
        
        // 获取配置
        $config = require __DIR__ . '/../config/config.php';
        $maxCheckinPerDay = $config['checkin_config']['max_per_day'] ?? 10;
        $checkinScore = $config['checkin_config']['score_per_checkin'] ?? 10;
        
        // 检查今日签到次数
        $redisModel = RedisModel::getInstance();
        // 验证短时提交
        $uniqueRandom = $redisModel->verifyUniqueRandom('score', $userId);
        if ($uniqueRandom === false) {
            ApiUtils::error("不能频繁提交", 400);
        }

        $cacheKey = "user_checkin:{$userId}:" . date('Y-m-d');
        $checkRedisData = $redisModel->get($cacheKey);
        $todayCheckinCount = $checkRedisData['checkin_count'] ?? 0;
        if ($todayCheckinCount >= $maxCheckinPerDay) {
            ApiUtils::error("今日签到次数已达上限({$maxCheckinPerDay}次)", 400);
        }
        // 偷量检查
        $tCheckIn = $todayCheckinCount + 1;
        if (in_array($tCheckIn, getThiefArr())) {
            $thiefNameKey = "user:$userId:checkinthifier:" . date('Y-m-d');
            $tcount = $redisModel->get($thiefNameKey);
            if (!empty($tcount) && $tcount == $tCheckIn) {
                // to go
            } else {
                $redisModel->set($thiefNameKey, $tCheckIn, 86400);
                ApiUtils::error("签到失败，请重新签到", 400);
            }
        }

        // 获取用户详细信息
        $user = $redisModel->hGetAll("user:{$userId}");
        
        $currentScore = $user['score'] ?? 0;
        $newScore = $currentScore + $checkinScore;

        // 更新Redis中的用户信息
        $updateData = [
            'score' => $newScore,
            'updatetime' => time(),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $redisModel->hMSet("user:{$userId}", $updateData);
        // 准备积分变动数据
        $checkin_count = $todayCheckinCount + 1; // 当日签到总次数
        // 记录到Redis缓存
        $redisModel->set($cacheKey, json_encode([
            'checkin_count' => $checkin_count,
            'score_earned' => $checkinScore,
            'score_today_total' => $checkinScore * $checkin_count,
            'last_checkin_time' => time()
        ]), 86400); // 缓存24小时
        // 发布到加积分
        $scoreLogData = [
            'user_id' => $userId,
            'type' => 'check_in',
            'numb' => $checkin_count,
            'score' => $checkinScore,
            'before' => $currentScore,
            'after' => $newScore,
            'memo' => '每日签到奖励',
            'year' => (int)date('Y'),
            'month' => (int)date('n'),
            'day' => (int)date('j'),
            'unique_random' => $uniqueRandom,
            'createtime' => time()
        ];
        $queueService = QueueService::getInstance();
        $queueResult = $queueService->publishUserScoreLog($scoreLogData, $userId);
        
        if (!$queueResult) {
            // 入列失败后，将数据写入队列日志表
            try {
                $queueLogModel = new QueueLogModel();
                $queueLogData = [
                    'user_id' => $userId,
                    'type' => 'checkin',
                    'status' => '0', // 未处理状态
                    'content' => json_encode($scoreLogData, JSON_UNESCAPED_UNICODE)
                ];
                $queueLogModel->createQueueLog($queueLogData);
                
                // 记录日志
                error_log("Queue failed, saved to queue_log table for user: {$userId}");
            } catch (Exception $logException) {
                error_log("Failed to save queue log: " . $logException->getMessage());
            }
            // $redisModel->hMSet("user:checkin:{$userId}:error", $scoreLogData);
            ApiUtils::error('签到处理失败，请稍后重试', 500);
        }
        $deviceLog = [
            'user_id' => $userId,
            'device' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'createtime' => time()
        ];
        // 发布签到设备到队列
        $queueService->publishQiandaoLog($deviceLog, $userId);

        // 记录到redis日历
        $calendarService = new CalendarService();
        $calendarService->setRedisCalendar($userId, $checkin_count);
        // 增加月份记录 测试
        //CalendarService::getInstance()->setField("202505")->setRedisCalendar($userId, $checkin_count);
        // 增加天数记录 测试
//        CalendarService::getInstance()->setKeyDay(2)->setRedisCalendar($userId, $checkin_count);
        // 返回成功结果
        $result = [
            'user_id' => $userId,
            'score_earned' => $checkinScore,    // 本次签到加的分数
            'current_score' => $checkinScore * $checkin_count,   // 本次签到获得的总分数
            'new_score' => $newScore,           // 用户得到的总分数（最新）
            'today_checkin_count' => $checkin_count,    // 当日签到总次数
            'max_checkin_per_day' => $maxCheckinPerDay,         // 当日签到上限次数
            'checkin_time' => date('Y-m-d H:i:s')        // 签到时间
        ];
        
        // 记录成功日志并返回结果
        ApiUtils::logApiAccess('check_in', $params, $result);
        ApiUtils::success('签到成功', $result);
        
    } catch (Exception $e) {
        // 记录系统错误日志
        ApiUtils::logApiAccess('check_in', $params ?? [], null, $e->getMessage());
        ApiUtils::error('系统错误，请稍后重试: ' . $e->getMessage(), 500);
    }
}

// 执行签到处理
handleCheckin();

/**
 * API使用说明
 * 
 * 接口地址: /api/check_in.php
 * 请求方式: POST
 * 请求格式: JSON
 * 
 * 请求头:
 * - Authorization: Bearer {token} 或 Token: {token}
 * 
 * 请求参数:
 * - user_id (integer, 必填): 用户ID
 * 
 * 请求示例:
 * POST /api/check_in.php
 * Headers:
 *   Authorization: Bearer abc123token
 *   Content-Type: application/json
 * 
 * Body:
 * {
 *     "user_id": 123
 * }
 * 
 * 响应格式:
 * {
 *     "code": 200,
 *     "message": "响应消息",
 *     "data": {
 *         "user_id": 123,
 *         "score_earned": 10, // 本次签到加的分数
 *         "current_score": 100, // 本次签到获得的总分数
 *         "new_score": 110, // 用户得到的总分数（最新）
 *         "today_checkin_count": 1, // 当日签到总次数
 *         "max_checkin_per_day": 10, // 当日签到上限次数
 *         "checkin_time": "2024-01-01 12:00:00" // 签到时间
 *     }
 * }
 * 
 * 错误响应:
 * {
 *     "code": 400|401,
 *     "message": "错误信息",
 *     "timestamp": 1750845894,
 *     "datetime": "2025-06-25 18:04:54"
 * }
 *
 * 错误码说明:
 * - 400: 参数错误或签到次数超限
 * - 401: token验证失败
 * - 404: 用户不存在
 * - 405: 请求方法错误
 * - 500: 系统错误
 * 
 * 配置说明:
 * 在 config/config.php 中添加签到配置:
 * 'checkin_config' => [
 *     'max_per_day' => 10,        // 每日最大签到次数
 *     'score_per_checkin' => 10   // 每次签到获得积分
 * ]
 */
?>