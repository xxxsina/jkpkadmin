<?php
/**
 * 获取用户签到页初始化数据接口
 *
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

// 引入依赖
require_once __DIR__ . '/../utils/ApiUtils.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../models/RedisModel.php';
require_once __DIR__ . '/../config/common.php';

// 处理CORS
ApiUtils::handleCors();

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiUtils::error('只支持POST请求', 405);
}

/**
 * 处理获取签到日历请求
 */
function handleGetCalendar() {
    try {
        // 获取请求参数
        $params = ApiUtils::getRequestParams(['user_id']);

        $userId = $params['user_id'] ?? 0;
        if (!empty($userId)) {
            // 从header中获取token
            $token = ApiUtils::getTokenFromHeader();
            if (empty($token)) {
                ApiUtils::unauthorized('缺少访问令牌，请在请求头中提供token');
            }
            // 验证token
            $userService = new UserService();
            if (!$userService->validateUserToken($userId, $token)) {
                ApiUtils::error('token验证失败', 401);
            }
        }

        // 获取配置
        $config = require __DIR__ . '/../config/config.php';
        $maxCheckinPerDay = $config['checkin_config']['max_per_day'] ?? 10;
        $checkinScore = $config['checkin_config']['score_per_checkin'] ?? 10;
        $maxMoreAgainScore = $config['checkin_config']['max_score_again_more'] ?? 10;
        $moreScore    = $config['checkin_config']['score_again_more'] ?? 10;

        // 获取用户详细信息
        $redisModel = RedisModel::getInstance();

        // 获取当日签到次数
        $todayCheckinCount = 0;
        if (!empty($userId)) {
            $cacheKey = "user_checkin:{$userId}:" . date('Y-m-d');
            $checkRedisData = $redisModel->get($cacheKey);
            if (!empty($checkRedisData)) {
                $todayCheckinCount = $checkRedisData['checkin_count'] ?? 0;
            }
        }
        // 获取当日赚取更多积分次数
        $todayAddMoreCount = 0;
        if (!empty($userId)) {
            $cacheKey = "user_add_score:{$userId}:" . date('Y-m-d');
            $addMoreRedisData = $redisModel->get($cacheKey);
            if (!empty($checkRedisData)) {
                $todayAddMoreCount = $addMoreRedisData['add_count'] ?? 0;
            }
        }

        // 返回成功结果
        $result = [
            'user_id' => $userId,
            'score_earned' => $checkinScore,                // 单次签到获得的分数
            'max_score_again_more' => $maxMoreAgainScore,   // 赚取更多积分 每日最大获奖次数
            'today_score_again_more' => $todayAddMoreCount, // 赚取更多积分 当日获奖总次数
            'score_again_more' => $moreScore,               // 赚取更多积分 单次获奖的分数
            'today_checkin_count' => $todayCheckinCount,    // 当日签到总次数
            'max_checkin_per_day' => $maxCheckinPerDay,     // 当日签到上限次数
            'notice_message' => getNoticeMessage(),         // 通知消息
        ];

        // 记录成功日志并返回结果
        ApiUtils::logApiAccess('check_in_init', $params, $result);
        ApiUtils::success('获取签到日历成功', $result);

    } catch (Exception $e) {
        // 记录系统错误日志
        ApiUtils::logApiAccess('check_in_init', $params ?? [], null, $e->getMessage());
        ApiUtils::error('系统错误，请稍后重试: ' . $e->getMessage(), 500);
    }
}

// 执行获取签到日历处理
handleGetCalendar();

/**
 * API使用说明
 *
 * 接口地址: /api/check_in_init.php
 * 请求方式: POST
 * 请求格式: Query Parameters
 *
 * 请求头:
 * - Authorization: Bearer {token} 或 Token: {token}
 *
 * 请求参数:
 * - user_id (integer, 未登录为0): 用户ID
 *
 * 请求示例:
 * {
 * "user_id": 123
 * }
 *
 * 响应格式:
 * {
 *     "code": 200,
 *     "message": "获取签到日历成功",
 *     "data": {
 *         "user_id": 123,
 *         "score_earned": 10, // 单次签到获得的分数
 *         "max_score_again_more": 10, // 赚取更多积分 每日最大获奖次数
 *         "today_score_again_more": 1, // 赚取更多积分 当日获奖总次数
 *         "score_again_more": 10, // 赚取更多积分 单次获奖的分数
 *         "today_checkin_count": 1, // 当日签到总次数
 *         "max_checkin_per_day": 10, // 当日签到上限次数
 *         "notice_message": "202412", // 通知消息
 *     }
 * }
 *
 *
 * 错误响应:
 * {
 *     "code": 400,
 *     "message": "参数错误",
 *     "timestamp": 1640995200,
 *     "datetime": "2024-01-01 12:00:00"
 * }
 *
 * 状态码说明:
 * - 200: 成功
 * - 400: 参数错误
 * - 401: token验证失败
 * - 405: 请求方法错误
 * - 500: 服务器内部错误
 *
 * 注意事项:
 * 1. user_id必须为有效的整数,如果没有登录传0
 */