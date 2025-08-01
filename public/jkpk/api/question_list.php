<?php
/**
 * 常见问题列表接口
 * 获取常见问题列表，支持分页
 * 使用MySQL数据库存储，按时间倒序返回
 */

require_once __DIR__ . '/../utils/ApiUtils.php';
require_once __DIR__ . '/../models/QuestionModel.php';
$config = require_once __DIR__ . '/../config/config.php';

// 处理CORS
ApiUtils::handleCors();

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        handleGetQuestionList();
    } else {
        ApiUtils::error('不支持的请求方法', 405);
    }
} catch (Exception $e) {
    error_log('Question List API Error: ' . $e->getMessage());
    ApiUtils::error('服务器内部错误', 500);
}

/**
 * 处理获取常见问题列表请求
 */
function handleGetQuestionList() {
    // 获取请求参数
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    if ($page < 1) {
        $page = 1;
    }
    
    // 每页显示10条
    $pageSize = 10;
    $offset = ($page - 1) * $pageSize;
    
    try {
        // 创建常见问题模型实例
        $questionModel = new QuestionModel();
        
        // 获取问题列表
        $questions = $questionModel->getQuestions($pageSize, $offset);
        
        // 格式化问题数据
        $formattedQuestions = [];
        foreach ($questions as $question) {
            // 格式化媒体文件URL
            $question['file'] = formatMediaUrl($question['file'] ?? '', 'video');
            
            // 转换数值字段
            $question['id'] = intval($question['id']);
            
            $formattedQuestions[] = $question;
        }
        
        // 获取总数量
        $totalCount = $questionModel->getTotalCount();
        $totalPages = ceil($totalCount / $pageSize);
        
        // 返回结果
        $result = [
            'list' => $formattedQuestions,
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
        ApiUtils::error('获取常见问题列表失败，请稍后重试', 500);
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

1. 获取常见问题列表:
   GET http://jiankangpaika.blcwg.com/jkpk/api/question_list.php?page=1

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
                "file": "http://shbadmin.blcwg.com/data/videos/question_video_1.mp4",
                "title": "常见问题标题",
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
    "message": "获取常见问题列表失败，请稍后重试",
    "timestamp": 1752411019,
    "datetime": "2025-07-13 20:50:19"
}

字段说明:
- id: 问题ID
- file: 教学视频URL（完整地址）
- title: 问题标题
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
1. 获取第一页: GET /api/question_list.php
2. 获取第二页: GET /api/question_list.php?page=2
3. 获取指定页: GET /api/question_list.php?page=5

注意事项:
- 只返回状态为启用(switch=1)的问题
- 按创建时间倒序排列
- 页码从1开始，无效页码自动修正为1
- 媒体文件URL自动添加域名前缀

接口特点:
- GET请求方式，简单易用
- 支持分页查询，每页固定10条记录
- 自动格式化时间和媒体URL
- 完善的错误处理和日志记录
- 遵循项目统一的API响应格式
 */
?>