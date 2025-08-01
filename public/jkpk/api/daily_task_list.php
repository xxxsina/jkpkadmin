<?php
/**
 * 每日任务列表接口
 * 获取每日任务列表，支持分页
 * 使用MySQL数据库存储，按时间倒序返回
 */

require_once __DIR__ . '/../utils/ApiUtils.php';
require_once __DIR__ . '/../models/DailyTaskModel.php';
$config = require_once __DIR__ . '/../config/config.php';

// 处理CORS
ApiUtils::handleCors();

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        handleGetDailyTaskList();
    } else {
        ApiUtils::error('不支持的请求方法', 405);
    }
} catch (Exception $e) {
    error_log('Daily Task List API Error: ' . $e->getMessage());
    ApiUtils::error('服务器内部错误', 500);
}

/**
 * 处理获取每日任务列表请求
 */
function handleGetDailyTaskList() {
    // 获取请求参数
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    if ($page < 1) {
        $page = 1;
    }
    
    // 每页显示10条
    $pageSize = 10;
    $offset = ($page - 1) * $pageSize;
    
    try {
        // 创建每日任务模型实例
        $dailyTaskModel = new DailyTaskModel();
        
        // 获取任务列表
        $tasks = $dailyTaskModel->getDailyTasks($pageSize, $offset);
        
        // 格式化任务数据
        $formattedTasks = [];
        foreach ($tasks as $task) {
            // 格式化媒体文件URL
            $task['file'] = formatMediaUrl($task['file'] ?? '', 'video');
            $task['image'] = formatMediaUrl($task['image'] ?? '', 'image');

            // 转换数值字段
            $task['id'] = intval($task['id']);
            
            $formattedTasks[] = $task;
        }
        
        // 获取总数量
        $totalCount = $dailyTaskModel->getTotalCount();
        $totalPages = ceil($totalCount / $pageSize);
        
        // 返回结果
        $result = [
            'list' => $formattedTasks,
            'pagination' => [
                'current_page' => $page,
                'page_size' => $pageSize,
                'total_count' => $totalCount,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ];
        
        ApiUtils::success('获取成功', $result);
        
    } catch (Exception $e) {
        error_log('MySQL Error: ' . $e->getMessage());
        ApiUtils::error('获取每日任务列表失败，请稍后重试', 500);
    }
}

/**
 * 格式化媒体文件URL
 * @param string $mediaUrl 媒体文件URL
 * @param string $type 媒体类型 (image/video)
 * @return string 格式化后的URL
 */
function formatMediaUrl($mediaUrl, $type = 'image') {
    global $config;
    if (empty($mediaUrl)) {
        return '';
    }

    // 外部URL，直接返回
    if (filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
        return $mediaUrl;
    }

    // 本地文件，返回完整HTTP地址
//    $basePath = $type === 'video' ? '/data/videos' : '/data/images';
    return $config['HTTP_HOST_ADMIN'] . $mediaUrl;
}

/**
 * API使用说明:

1. 获取每日任务列表:
   GET http://jiankangpaika.blcwg.com/jkpk/api/daily_task_list.php?page=1

请求参数:
- page (int, 可选): 页码，默认为1

响应格式:
{
    "code": 200,
    "message": "获取成功",
    "data": {
        "list": [
            {
                "id": 1,
                "file": "http://shbadmin.blcwg.com/data/videos/task_video_1.mp4",
                "url": "https://example.com/task1",
                "title": "每日任务标题",
                "image": "http://shbadmin.blcwg.com/data/images/task_cover_1.jpg",
                "switch": 1,
                "createtime": 1752411019,
                "updatetime": 1752411019,
            }
        ],
        "pagination": {
            "current_page": 1,
            "page_size": 10,
            "total_count": 25,
            "total_pages": 3,
            "has_next": true,
            "has_prev": false
        }
    },
    "timestamp": 1752411019,
    "datetime": "2025-07-13 20:50:19"
}

失败响应:
{
    "code": 500,
    "message": "获取每日任务列表失败，请稍后重试",
    "timestamp": 1752411019,
    "datetime": "2025-07-13 20:50:19"
}

字段说明:
- id: 任务ID
- file: 教学视频URL（完整地址）
- url: 融码URL
- title: 任务标题
- image: 封面图片URL（完整地址）
- switch: 状态（1=启用，0=禁用）
- createtime: 创建时间戳
- updatetime: 更新时间戳

分页说明:
- current_page: 当前页码
- page_size: 每页条数（固定10条）
- total_count: 总记录数
- total_pages: 总页数
- has_next: 是否有下一页
- has_prev: 是否有上一页

媒体文件URL配置:
- 基础域名: http://shbadmin.blcwg.com/
- 视频路径: /data/videos/
- 图片路径: /data/images/
- 支持外部URL直接返回

使用示例:
1. 获取第一页: GET /api/daily_task_list.php
2. 获取第二页: GET /api/daily_task_list.php?page=2
3. 获取指定页: GET /api/daily_task_list.php?page=5

注意事项:
- 只返回状态为启用(switch=1)的任务
- 按创建时间倒序排列
- 页码从1开始，无效页码自动修正为1
- 媒体文件URL自动添加域名前缀

*/

?>