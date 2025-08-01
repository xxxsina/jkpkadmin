<?php
/**
 * 获取用户签到日历API接口
 * 获取用户当月签到数据
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

// 引入依赖
require_once __DIR__ . '/../utils/ApiUtils.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../services/CalendarService.php';
require_once __DIR__ . '/../models/RedisModel.php';
require_once __DIR__ . '/../config/common.php';

// 处理CORS
ApiUtils::handleCors();

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiUtils::error('只支持POST请求', 405);
}

/**
 * 定义获取日历参数验证规则
 */
function getCalendarValidationRules() {
    return [
        'user_id' => 'required|integer',
        'field' => 'optional|string' // 可选参数，格式如202412
    ];
}

/**
 * 处理获取签到日历请求
 */
function handleGetCalendar() {
    try {
        // 获取请求参数
        $params = ApiUtils::getRequestParams(['user_id', 'field']);

        // 从header中获取token
        $token = ApiUtils::getTokenFromHeader();
        if (empty($token)) {
            ApiUtils::unauthorized('缺少访问令牌，请在请求头中提供token');
        }

        // 验证参数
        $rules = getCalendarValidationRules();
        $errors = ApiUtils::validateParams($params, $rules);
        
        if (!empty($errors)) {
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                $errorMessages[] = implode(', ', $fieldErrors);
            }
            ApiUtils::error('参数验证失败: ' . implode('; ', $errorMessages), 400);
        }

        $userId = $params['user_id'];
        $field = $params['field'] ?? date('Ym'); // 默认当月

        // 验证token
        $userService = new UserService();
        if (!$userService->validateUserToken($userId, $token)) {
            ApiUtils::error('token验证失败', 401);
        }
        
        // 获取配置
        $config = require __DIR__ . '/../config/config.php';
        $maxCheckinPerDay = $config['checkin_config']['max_per_day'] ?? 10;
        $checkinScore = $config['checkin_config']['score_per_checkin'] ?? 10;
        $moreScore = $config['checkin_config']['score_again_more'] ?? 10;

        // 获取用户详细信息
        $redisModel = RedisModel::getInstance();
        $user = $redisModel->hGetAll("user:{$userId}");
        $currentScore = $user['score'] ?? 0;
        
        // 获取当日签到次数
        $cacheKey = "user_checkin:{$userId}:" . date('Y-m-d');
        $checkRedisData = $redisModel->get($cacheKey);
        $scoreTodayTotal = 0;
        $todayCheckinCount = 0;
        $lastCheckinTime = date('Y-m-d H:i:s');
        if (!empty($checkRedisData)) {
            $todayCheckinCount = $checkRedisData['checkin_count'] ?? 0;
            // 最后签到时间
            $lastCheckinTime = !empty($checkRedisData['last_checkin_time'])
                ? date('Y-m-d H:i:s', $checkRedisData['last_checkin_time'])
                : $lastCheckinTime;
            // 当日签到获得的总分数
            $scoreTodayTotal = $checkRedisData['score_today_total'] ?? 0;
        }
        
        // 获取签到日历数据
        $calendarService = CalendarService::getInstance();
        $calendarField = $calendarService->setRname($userId)->getRedisCalendarField(null, $field);

        // 计算新分数（模拟签到后的分数）
//        $newScore = $currentScore + $checkinScore;

        // 返回成功结果
        $result = [
            'user_id' => $userId,
            'score_earned' => $checkinScore,    // 单次签到获得的分数
            'score_again_more' => $moreScore,         // 赚取更多积分单次获得的分数
            'current_score' => $scoreTodayTotal,   // 当日签到获得的总分数
            'new_score' => $currentScore,       // 用户得到的总分数（最新）
            'today_checkin_count' => $todayCheckinCount,    // 当日签到总次数
            'max_checkin_per_day' => $maxCheckinPerDay,     // 当日签到上限次数
            'checkin_time' => $lastCheckinTime,         // 最后签到时间
            'calendar_data' => !empty($calendarField)? $calendarField : new stdClass(), // 当月签到日历数据
            'field' => $field,                               // 查询的月份
            'notice_message' => getNoticeMessage(),              // 通知消息
        ];
        
        // 记录成功日志并返回结果
        ApiUtils::logApiAccess('get_calendar', $params, $result);
        ApiUtils::success('获取签到日历成功', $result);
        
    } catch (Exception $e) {
        // 记录系统错误日志
        ApiUtils::logApiAccess('get_calendar', $params ?? [], null, $e->getMessage());
        ApiUtils::error('系统错误，请稍后重试: ' . $e->getMessage(), 500);
    }
}

// 执行获取签到日历处理
handleGetCalendar();

/**
 * API使用说明
 * 
 * 接口地址: /api/get_calendar.php
 * 请求方式: GET
 * 请求格式: Query Parameters
 * 
 * 请求头:
 * - Authorization: Bearer {token} 或 Token: {token}
 * 
 * 请求参数:
 * - user_id (integer, 必填): 用户ID
 * - field (string, 可选): 查询月份，格式YYYYMM，如202412，默认当月
 * 
 * 请求示例:
 * GET /api/get_calendar.php?user_id=123&field=202412
 * Headers:
 *   Authorization: Bearer abc123token
 * 
 * 响应格式:
 * {
 *     "code": 200,
 *     "message": "获取签到日历成功",
 *     "data": {
 *         "user_id": 123,
 *         "score_earned": 10, // 单次签到获得的分数
 *         "score_again_more": 10, // 赚取更多积分单次获得的分数
 *         "current_score": 100, // 当日签到获得的总分数
 *         "new_score": 110, // 用户得到的总分数（最新）
 *         "today_checkin_count": 1, // 当日签到总次数
 *         "max_checkin_per_day": 10, // 当日签到上限次数
 *         "checkin_time": "2024-12-26 12:00:00", // 最后一次签到时间
 *         "calendar_data": { // 当月签到日历数据
 *             "1": {
 *                  "count": 1, // 签到1次
 *                  "is_complete": 0 // 是否完成签到，1是，0否
 *             },
 *             "2": {
 *                   "count": 10,    // 签到10次
 *                   "is_complete": 1 // 是否完成签到，1是，0否
 *              },
 *             "3": {
 *                   "count": 4,    // 签到4次
 *                   "is_complete": 0 // 是否完成签到，1是，0否
 *              }
 *         },
 *         "field": "202412", // 查询的月份
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
 * 1. 必须在请求头中提供有效的token
 * 2. user_id必须为有效的整数
 * 3. field参数格式必须为YYYYMM，如202412
 * 4. 返回的calendar_data中，key为日期（1-31），value为数组包含count和is_complete字段，count表示签到次数，is_complete表示是否完成当日签到
 * 5. 如果某日未签到，则calendar_data中不包含该日期的数据
 * 6. 如果查询的月份不存在，则calendar_data为[]
 */