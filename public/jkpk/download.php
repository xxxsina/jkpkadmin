
<?php
/**
 * 动态下载页面
 * 自动从version_server.php获取最新版本信息
 */
// 先设置了这个，才不会起冲突
$_GET['action'] = 'download';

// 引入版本服务器配置
require_once __DIR__ . '/api/version_server.php';

// 获取最新版本信息
$versionAPI = new VersionUpdateAPI();
$versionResponse = $versionAPI->checkUpdate(-1); // 传入0获取最新版本
$latestVersion = $versionResponse['data'] ?? null;

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>健康派卡APP下载 - 全球好物随心购</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }

        body {
            background-color: #f8f9fa;
        }

        .header {
            background-color: #673AB7;
            color: white;
            padding: 1rem;
            text-align: center;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 20px;
        }

        .content {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }

        .app-info {
            flex: 1;
            min-width: 300px;
        }

        .app-image {
            flex: 1;
            text-align: center;
            min-width: 300px;
        }

        h1 {
            color: #673AB7;
            margin-bottom: 1.5rem;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin: 2rem 0;
        }

        .feature-item {
            padding: 1rem;
            background: #f4f4f4;
            border-radius: 8px;
        }

        .download-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .download-btn {
            flex: 1;
            padding: 1rem 2rem;
            border: none;
            border-radius: 25px;
            color: white;
            font-size: 1.1rem;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            min-width: 200px;
        }

        .android {
            background-color: #32CD32;
        }

        .ios {
            background-color: #007AFF;
        }

        .qrcode {
            margin-top: 2rem;
            text-align: center;
        }

        .qrcode img {
            width: 180px;
            height: 180px;
        }

        footer {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        @media (max-width: 768px) {
            .container {
                margin: 1rem auto;
                padding: 0 15px;
            }

            .content {
                padding: 1.5rem;
                gap: 1.5rem;
            }

            .features {
                grid-template-columns: 1fr;
                gap: 0.8rem;
                margin: 1.5rem 0;
            }

            .feature-item {
                padding: 0.8rem;
                font-size: 0.9rem;
            }

            .app-image {
                order: -1;
            }

            .download-buttons {
                flex-direction: column;
                gap: 0.8rem;
            }

            .download-btn {
                min-width: auto;
                width: 100%;
                padding: 1.2rem 1rem;
                font-size: 1rem;
            }

            h1 {
                font-size: 1.8rem;
                margin-bottom: 1rem;
            }

            .header {
                padding: 1.5rem 1rem;
            }

            .header h2 {
                font-size: 1.5rem;
            }

            .header p {
                font-size: 0.9rem;
                margin-top: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 10px;
            }

            .content {
                padding: 1rem;
                margin: 0.5rem 0;
            }

            h1 {
                font-size: 1.5rem;
            }

            .download-btn {
                padding: 1rem;
                font-size: 0.95rem;
            }

            .feature-item {
                padding: 0.6rem;
                font-size: 0.85rem;
            }

            .header {
                padding: 1rem;
            }

            .header h2 {
                font-size: 1.3rem;
            }

            footer {
                padding: 1.5rem 1rem;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
<header class="header">
    <h2>健康派卡</h2>
    <p>健康生活 养生运动</p>
</header>

<div class="container">
    <div class="content">
        <div class="app-info">
            <h1>健康派卡APP下载</h1>
            <p>下载健康派卡APP，畅享优质生活品质，新人注册即领188元大礼包！</p>

            <div class="features">
                <div class="feature-item">✔ 每日专属优惠</div>
                <div class="feature-item">✔ 享受优质生活</div>
                <div class="feature-item">✔ 独占品质服务</div>
                <div class="feature-item">✔ 会员专属特权</div>
            </div>

            <div class="download-buttons">
                <a href="<?php echo htmlspecialchars($latestVersion['downloadUrl']); ?>" class="download-btn android">
                    安卓版下载
                </a>
            </div>
            
            <div style="margin-top: 1rem; padding: 1rem; background: #e8f5e8; border-radius: 8px; font-size: 0.9rem;">
                <strong>版本信息：</strong><br>
                版本号：v<?php echo htmlspecialchars($latestVersion['versionName']); ?><br>
                文件大小：<?php echo htmlspecialchars($latestVersion['fileSize']); ?><br>
                <?php if (isset($latestVersion['updateMessage'])): ?>
                更新内容：<?php echo nl2br(htmlspecialchars($latestVersion['updateMessage'])); ?>
                <?php endif; ?>
            </div>

        </div>


    </div>
</div>

<footer>
    <p>© 2024 健康派卡 版权所有</p>
    <p>客服电话：400-123-4567 | 粤ICP备2024567890号</p>
</footer>
</body>
</html>
