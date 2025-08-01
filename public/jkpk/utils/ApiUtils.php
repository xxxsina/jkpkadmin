<?php
/**
 * API工具类
 * 提供统一的API响应、日志记录、参数处理等功能
 * 
 * @author 健康派卡开发团队
 * @version 1.0
 * @date 2024-01-01
 */

class ApiUtils {
    
    /**
     * 统一API响应格式
     * 
     * @param int $code 响应码 (200=成功, 400=参数错误, 401=未授权, 500=服务器错误)
     * @param string $message 响应消息
     * @param mixed $data 响应数据
     * @param array $extra 额外信息
     */
    public static function sendResponse($code, $message, $data = null, $extra = []) {
        // 设置响应头
        header('Content-Type: application/json; charset=utf-8');
        
        // 根据状态码设置HTTP状态
        $httpStatus = self::getHttpStatus($code);
        http_response_code($httpStatus);
        
        // 构建响应数据
        $response = [
            'code' => $code,
            'message' => $message,
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s')
        ];
        
        // 添加数据（如果有）
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        // 添加额外信息
        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }
        
        // 输出JSON响应
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * 成功响应
     */
    public static function success($message = '操作成功', $data = null, $extra = []) {
        self::sendResponse(200, $message, $data, $extra);
    }
    
    /**
     * 错误响应
     */
    public static function error($message = '操作失败', $code = 400, $data = null, $extra = []) {
        self::sendResponse($code, $message, $data, $extra);
    }
    
    /**
     * 参数错误响应
     */
    public static function paramError($message = '参数错误', $errors = []) {
        $extra = [];
        if (!empty($errors)) {
//            $extra['errors'] = $errors;
        }
        self::sendResponse(400, $message, null, $extra);
    }
    
    /**
     * 未授权响应
     */
    public static function unauthorized($message = '未授权访问') {
        self::sendResponse(401, $message);
    }
    
    /**
     * 服务器错误响应
     */
    public static function serverError($message = '服务器内部错误') {
        self::sendResponse(500, $message);
    }
    
    /**
     * 获取HTTP状态码
     */
    private static function getHttpStatus($code) {
        $statusMap = [
            200 => 200, // 成功
            400 => 400, // 请求错误
            401 => 401, // 未授权
            403 => 403, // 禁止访问
            404 => 404, // 未找到
            500 => 500, // 服务器错误
        ];
        
        return isset($statusMap[$code]) ? $statusMap[$code] : 200;
    }
    
    /**
     * 记录API访问日志
     * 
     * @param string $api API名称
     * @param array $params 请求参数
     * @param string $result 处理结果
     * @param float $duration 处理时长（秒）
     */
    public static function logApiAccess($api, $params = [], $result = 'success', $duration = 0) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'api' => $api,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'ip' => self::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'params' => $params,
            'result' => $result,
            'duration' => round($duration, 4),
            'memory_usage' => self::formatBytes(memory_get_usage()),
            'memory_peak' => self::formatBytes(memory_get_peak_usage())
        ];
        
        $logMessage = sprintf(
            "[API] %s %s | IP: %s | Result: %s | Duration: %.4fs | Memory: %s",
            $logData['method'],
            $api,
            $logData['ip'],
            $result,
            $duration,
            $logData['memory_usage']
        );
        
        error_log($logMessage);
        
        // 可选：写入专门的API日志文件
        // self::writeApiLog($logData);
    }
    
    /**
     * 获取客户端IP地址
     */
    public static function getClientIp() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * 获取请求参数
     * 支持GET、POST、PUT、DELETE等方法
     * 
     * @param array $requiredParams 必需参数列表
     * @param array $optionalParams 可选参数列表（带默认值）
     * @return array 处理后的参数数组
     */
    public static function getRequestParams($requiredParams = [], $optionalParams = []) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $params = [];

        // 根据请求方法获取参数
        switch (strtoupper($method)) {
            case 'GET':
                $params = $_GET;
                break;
                
            case 'POST':
                // 检查Content-Type
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
                if (strpos($contentType, 'application/json') !== false) {
                    // JSON格式
                    $input = file_get_contents('php://input');
                    $jsonData = json_decode($input, true);
                    $params = $jsonData ?: [];
                } else {
                    // 表单格式
                    $params = $_POST;
                }
                break;
                
            case 'PUT':
            case 'DELETE':
            case 'PATCH':
                // 从php://input获取数据
                $input = file_get_contents('php://input');
                parse_str($input, $params);
                
                // 尝试解析JSON
                if (empty($params)) {
                    $jsonData = json_decode($input, true);
                    $params = $jsonData ?: [];
                }
                break;
                
            default:
                $params = array_merge($_GET, $_POST);
        }

        // 处理必需参数
        $result = [];
        $missingParams = [];

        foreach ($requiredParams as $param) {
            if (isset($params[$param]) && $params[$param] !== '') {
                $result[$param] = $params[$param];
            } else {
                $missingParams[] = $param;
            }
        }
        // 检查缺失的必需参数
        if (!empty($missingParams)) {
            self::paramError('缺少必需参数: ' . implode(', ', $missingParams), [
                'missing_params' => $missingParams
            ]);
        }
        
        // 处理可选参数
        foreach ($optionalParams as $param => $defaultValue) {
            $result[$param] = isset($params[$param]) ? $params[$param] : $defaultValue;
        }
        
        return $result;
    }
    
    /**
     * 验证参数
     * 
     * @param array $params 要验证的参数
     * @param array $rules 验证规则
     * @return array 验证错误信息（空数组表示验证通过）
     */
    public static function validateParams($params, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = isset($params[$field]) ? $params[$field] : null;
            $fieldErrors = self::validateField($field, $value, $rule);
            
            if (!empty($fieldErrors)) {
                $errors[$field] = $fieldErrors;
            }
        }

        return $errors;
    }
    
    /**
     * 验证单个字段
     */
    private static function validateField($field, $value, $rules) {
        $errors = [];
        
        // 如果规则是字符串，转换为数组
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        
        foreach ($rules as $rule) {
            // 解析规则和参数
            if (strpos($rule, ':') !== false) {
                list($ruleName, $ruleParam) = explode(':', $rule, 2);
            } else {
                $ruleName = $rule;
                $ruleParam = null;
            }
            
            $error = self::applyValidationRule($field, $value, $ruleName, $ruleParam);
            if ($error) {
                $errors[] = $error;
            }
        }
        
        return $errors;
    }
    
    /**
     * 应用验证规则
     */
    private static function applyValidationRule($field, $value, $rule, $param) {
        switch ($rule) {
            case 'required':
                if ($value === null || $value === '') {
                    return "{$field}不能为空";
                }
                break;
                
            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "邮箱格式不正确";
                }
                break;
                
            case 'phone':
                if ($value && !preg_match('/^1[3-9]\d{9}$/', $value)) {
                    return "手机号格式不正确";
                }
                break;
                
            case 'min':
                if ($value && strlen($value) < intval($param)) {
                    return "{$field}长度不能少于{$param}位";
                }
                break;
                
            case 'max':
                if ($value && strlen($value) > intval($param)) {
                    return "{$field}长度不能超过{$param}位";
                }
                break;
                
            case 'length':
                if ($value && strlen($value) !== intval($param)) {
                    return "{$field}长度必须为{$param}位";
                }
                break;
                
            case 'numeric':
                if ($value && !is_numeric($value)) {
                    return "{$field}必须为数字";
                }
                break;
                
            case 'alpha':
                if ($value && !preg_match('/^[a-zA-Z]+$/', $value)) {
                    return "{$field}只能包含字母";
                }
                break;
                
            case 'alphanumeric':
                if ($value && !preg_match('/^[a-zA-Z0-9]+$/', $value)) {
                    return "{$field}只能包含字母和数字";
                }
                break;
                
            case 'username':
                if ($value && !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $value)) {
                    return "账号格式不正确，只能包含字母、数字、下划线，长度3-20位";
                }
                break;
                
            case 'password':
                if ($value && (strlen($value) < 6 || strlen($value) > 50)) {
                    return "密码长度必须在6-50位之间";
                }
                break;
                
            case 'account':
                // 账号格式：邮箱、手机号或用户名
                if ($value) {
                    $isEmail = filter_var($value, FILTER_VALIDATE_EMAIL);
                    $isPhone = preg_match('/^1[3-9]\d{9}$/', $value);
                    $isUsername = preg_match('/^[a-zA-Z0-9_]{3,20}$/', $value);

                    // 添加调试日志
                    error_log("[DEBUG] Account validation for '{$value}': email=" . ($isEmail ? 'true' : 'false') . ", phone=" . ($isPhone ? 'true' : 'false') . ", username=" . ($isUsername ? 'true' : 'false'));
                    
                    if (!$isEmail && !$isPhone && !$isUsername) {
                        return "账号格式不正确，请输入邮箱、手机号或用户名(字母、数字、下划线)";
                    }
                }
                break;
                
            case 'in':
                $allowedValues = explode(',', $param);
                if ($value && !in_array($value, $allowedValues)) {
                    return "{$field}值无效，允许的值: " . implode(', ', $allowedValues);
                }
                break;
                
            case 'regex':
                if ($value && !preg_match($param, $value)) {
                    return "{$field}格式不正确";
                }
                break;
        }

        return null;
    }
    
    /**
     * 处理CORS预检请求
     */
    public static function handleCors($allowedOrigins = ['*'], $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']) {
        // 设置CORS头
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        }
        
        header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        
        // 处理OPTIONS预检请求
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * 格式化字节数
     */
    private static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * 写入API专用日志文件
     */
    private static function writeApiLog($logData) {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/api_' . date('Y-m-d') . '.log';
        $logLine = json_encode($logData, JSON_UNESCAPED_UNICODE) . "\n";
        
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * 生成请求ID（用于追踪）
     */
    public static function generateRequestId() {
        return uniqid('req_', true) . '_' . mt_rand(1000, 9999);
    }
    
    /**
     * 从请求头获取Token
     * 支持多种header格式：Authorization: Bearer token, Authorization: token, token: value
     * 
     * @return string|null 返回token值，如果未找到则返回null
     */
    public static function getTokenFromHeader() {
        // 方法1: 检查 Authorization header
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            
            // Bearer token格式
            if (preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
                return $matches[1];
            }
            
            // 直接token格式
            if (preg_match('/^[a-zA-Z0-9_\-\.]+$/', $authHeader)) {
                return $authHeader;
            }
        }
        
        // 方法2: 检查 token header
        if (isset($_SERVER['HTTP_TOKEN'])) {
            return $_SERVER['HTTP_TOKEN'];
        }
        
        // 方法3: 检查自定义header（兼容性）
        $customHeaders = ['HTTP_X_TOKEN', 'HTTP_X_AUTH_TOKEN', 'HTTP_ACCESS_TOKEN'];
        foreach ($customHeaders as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                return $_SERVER[$header];
            }
        }
        
        return null;
    }
    
    /**
     * 验证Token（示例方法）
     */
    public static function validateToken($token) {
        // 这里可以实现JWT验证或其他token验证逻辑
        if (empty($token)) {
            return false;
        }
        
        // 示例：简单的token格式验证
        return strlen($token) >= 32;
    }
    
    /**
     * 限流检查（示例方法）
     */
    public static function checkRateLimit($identifier, $maxRequests = 100, $timeWindow = 3600) {
        // 这里可以实现基于Redis的限流逻辑
        // 返回true表示允许请求，false表示超出限制
        return true;
    }
}