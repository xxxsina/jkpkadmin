<?php
/**
 * 上传服务类
 * 封装用户的上传相关的业务逻辑，包括上传图片、上传视频
 */


class UploadService {
    private static $instance = null;

    public function __construct() {

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
     * 处理图片上传
     */
    public function handleImageUpload($userId) {
        try {
            // 检查是否有文件上传
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['image'];
            } else {
                return [
                    'success' => false,
                    'message' => '请选择上传图片',
                    'code' => 400
                ];
            }

            // 检查文件大小（1MB = 1048576 bytes）
            if ($file['size'] > 1048576 * 5) {
                return [
                    'success' => false,
                    'message' => '上次图片大小不能超过5MB',
                    'code' => 400
                ];
            }

            // 检查文件类型
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowedTypes)) {
                return [
                    'success' => false,
                    'message' => '只支持JPG、PNG、GIF格式的图片',
                    'code' => 400
                ];
            }

            // 生成文件名
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'image_' . $userId . '_' . time() . '.' . strtolower($extension);

            // 确保目录存在
            $uploadDir = __DIR__ . '/../../uploads/' . date("Ymd");
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0757, true);
            }

            $filePath = $uploadDir . $fileName;
            // 移动上传的文件
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return [
                    'success' => false,
                    'message' => '图片上传失败',
                    'code' => 500
                ];
            }

            return [
                'success' => true,
                'image' => $fileName
            ];

        } catch (Exception $e) {
            error_log("[UploadService] 图片上传失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '图片上传失败',
                'code' => 500
            ];
        }
    }

    /**
     * 处理视频上传
     */
    public function handleVideoUpload($userId) {
        try {
            // 检查是否有视频上传
            if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['video'];
            } else {
                return [
                    'success' => false,
                    'message' => '请选择上传视频',
                    'code' => 400
                ];
            }

            // 检查文件大小（1MB = 1048576 bytes）
            if ($file['size'] > 1048576 * 50) {
                return [
                    'success' => false,
                    'message' => '上次视频大小不能超过50MB',
                    'code' => 400
                ];
            }

            // 检查文件类型
            $allowedTypes = ['video/mp4', 'video/avi', 'video/quicktime'];
            if (!in_array($file['type'], $allowedTypes)) {
                return [
                    'success' => false,
                    'message' => '只支持mp4、avi、mov格式的视频',
                    'code' => 400
                ];
            }

            // 生成视频名
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'video_' . $userId . '_' . time() . '.' . strtolower($extension);

            // 确保目录存在
            $uploadDir = __DIR__ . '/../../uploads/' . date("Ymd");
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0757, true);
            }

            $filePath = $uploadDir . $fileName;
            // 移动上传的视频
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return [
                    'success' => false,
                    'message' => '视频上传失败',
                    'code' => 500
                ];
            }

            return [
                'success' => true,
                'video' => $fileName
            ];

        } catch (Exception $e) {
            error_log("[UploadService] 视频上传失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '视频上传失败',
                'code' => 500
            ];
        }
    }
}