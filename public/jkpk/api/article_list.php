<?php
/**
 * 文章列表接口
 * 获取文章列表，支持分页
 * 使用Redis有序集合存储，按排序和时间倒序返回
 */

require_once __DIR__ . '/../utils/ApiUtils.php';
require_once __DIR__ . '/../models/RedisModel.php';
$config = require_once __DIR__ . '/../config/config.php';

// 处理CORS
ApiUtils::handleCors();

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        handleGetArticleList();
    } else {
        ApiUtils::error('不支持的请求方法', 405);
    }
} catch (Exception $e) {
    error_log('Article List API Error: ' . $e->getMessage());
    ApiUtils::error('服务器内部错误', 500);
}

/**
 * 处理获取文章列表请求
 */
function handleGetArticleList() {
    $redis = RedisModel::getInstance();
    
    // 获取请求参数
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    if ($page < 1) {
        $page = 1;
    }
    
    // 每页显示10条
    $pageSize = 10;
    $start = ($page - 1) * $pageSize;
    $end = $start + $pageSize - 1;
    
    try {
        // 获取文章列表的key（按排序和创建时间排序）
        $articlesKey = "articles:list";
        
        // 使用zRevRange获取文章ID列表（按分数倒序，分数为排序值+时间戳）
        $articleIds = $redis->zRange($articlesKey, $start, $end);
        
        $articles = [];
        
        // 获取每篇文章的详细信息
        foreach ($articleIds as $articleId) {
            $articleKey = "article:id:{$articleId}";
            $articleData = $redis->hGetAll($articleKey);
            $row = [];
            if (!empty($articleData)) {
                // 转换数值字段
                $row['id'] = intval($articleData['id']);
                $row['type'] = formatType($articleData['type']);
                $row['cover_image'] = formatMediaUrlAdmin($articleData['cover_image'] ?? ''); // 格式化封面图片URL
                $row['title'] = $articleData['title'];
                $row['content'] = $articleData['content'];

                $articles[] = $row;
            }
        }
        
        // 获取总数量
        $totalCount = $redis->zCard($articlesKey);
        $totalPages = ceil($totalCount / $pageSize);
        
        // 返回结果
        $result = [
            'list' => $articles,
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
        error_log('Redis Error: ' . $e->getMessage());
        ApiUtils::error('获取文章列表失败，请稍后重试', 500);
    }
}

/**
 * 格式化图片URL
 * @param string $imageUrl 图片URL
 * @return string 格式化后的URL
 */
function formatMediaUrlAdmin($mediaUrl, $type = 'image') {
    global $config;
    if (empty($mediaUrl)) {
        return '';
    }

    // 外部URL，直接返回
    if (filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
        return $mediaUrl;
    }

    // 本地文件，返回完整HTTP地址
    return $config['HTTP_HOST_ADMIN'] . $mediaUrl;
}

function formatType($type)
{
    $typeArr = [
        "sport" => "运动",
        "chinese_medical" => "中医",
        "science" => "科技",
        "food" => "食物",
    ];
    return isset($typeArr[$type]) ? $typeArr[$type] : '未知';
}

/**
 * API使用示例:

1. 获取文章列表:
   GET http://jiankangpaika.blcwg.com/jkpk/api/article_list.php?page=1

响应格式:
{
    "code": 200,
    "message": "获取成功",
    "data": {
        "list": [
            {
                "id": 1,
                "type": "运动",
                "title": "健康运动指南",
                "cover_image": "http://jiankangpaika.blcwg.com/data/images/cover1.jpg",
                "content": "完整的文章内容...",
            },
            {
                "id": 2,
                "type": "中医",
                "title": "养生知识",
                "cover_image": "http://jiankangpaika.blcwg.com/data/images/cover2.jpg",
                "content": "中医养生的基本原理...",
            }
        ],
        "pagination": {
            "current_page": 1,
            "page_size": 20,
            "total_count": 45,
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
    "message": "获取文章列表失败，请稍后重试",
    "timestamp": 1752411019,
    "datetime": "2025-07-13 20:50:19"
}

2. 获取第二页:
   GET http://jiankangpaika.blcwg.com/jkpk/api/article_list.php?page=2

3. 不传page参数（默认第一页）:
   GET http://jiankangpaika.blcwg.com/jkpk/api/article_list.php

注意事项:
- 该接口无需登录验证
- 每页固定返回10条记录
- 封面图片会自动转换为完整URL
 */
?>