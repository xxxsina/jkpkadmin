<?php
/**
 * 广告配置管理接口
 * 提供统一的广告开关控制，支持总开关和各类型广告的独立开关
 * 
 * 接口地址: http://jiankangpaika.blcwg.com/jkpk/api/ad_config.php
 * 请求方式: GET
 * 返回格式: JSON
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * 广告开关配置类
 */
class AdSwitchConfig {
    
    // 广告总开关
    public $masterSwitch = false;
    
    // 各类型广告开关
    public $splashAdSwitch = true;      // 开屏广告开关
    public $interstitialAdSwitch = false; // 插屏广告开关
    public $feedAdSwitch = true;        // 信息流广告开关
    public $rewardVideoAdSwitch = true; // 激励视频广告开关
    public $bannerAdSwitch = true;      // Banner广告开关
    public $drawAdSwitch = false;      // Braw广告开关

    // 广告平台选择 (kuaishou 或 chuanshanjia 或 taku )
    public $splashAdPlatform = "chuanshanjia";      // 开屏广告平台
    public $interstitialAdPlatform = "chuanshanjia"; // 插屏广告平台
    public $feedAdPlatform = "chuanshanjia";        // 信息流广告平台
    public $rewardVideoAdPlatform = "chuanshanjia"; // 激励视频广告平台
    public $bannerAdPlatform = "chuanshanjia";      // Banner广告平台
    public $drawAdPlatform = "chuanshanjia";      // Braw广告平台

    // 开启穿山甲GroMore配置
    public $openGroMore = true;

    // 快手广告配置
    public $kuaishouConfig = [
        'APP_ID' => '2652700003',
        'APP_NAME' => '健康派卡',
        'SPLASH' => '26527000008',       // 开屏广告
        'FEED' => '26527000009',         // 信息流广告
        'REWARD_VIDEO' => '26527000011',   // 激励视频广告
        'INTERSTITIAL' => '26527000010', // 插屏广告
        'BANNER' => '26527000013',       // Banner广告
        'DRAW_VIDEO' => '26527000014'    // Braw广告
    ];
//    public $kuaishouConfig = [
//        'APP_ID' => '90009',
//        'APP_NAME' => '健康派卡',
//        'SPLASH' => '4000000042',       // 开屏广告
//        'FEED' => '4000000079',         // 信息流广告
//        'REWARD_VIDEO' => '90009001',   // 激励视频广告
//        'INTERSTITIAL' => '4000000276', // 插屏广告
//        'BANNER' => '4000001623',       // Banner广告
//        'DRAW_VIDEO' => '4000000020'    // Braw广告
//    ];
    public $takuConfigGroMore = [
        'APP_ID' => 'a687e57e2e68d0',
        'APP_KEY' => 'af361b82bcdb21b0c88cbf630fd016b97',
        'APP_NAME' => '健康派卡',
        'SPLASH' => 'b687e61873b69a',        // 开屏广告
        'FEED' => 'b687e6160a5b18',          // 信息流广告
        'REWARD_VIDEO' => 'b687e60efa56a9',  // 激励视频广告
        'INTERSTITIAL' => 'b687e6100364a8',  // 插屏广告
        'BANNER' => 'b687e617160a6e',        // Banner广告
        'DRAW_VIDEO' => 'b6305efb12d408'     // Braw广告
//        'APP_ID' => 'a62b013be01931',
//        'APP_KEY' => 'c3d0d2a9a9d451b07e62b509659f7c97',
//        'APP_NAME' => '健康派卡',
//        'SPLASH' => 'b62b0272f8762f',        // 开屏广告
//        'FEED' => 'b62b028c2a217d',          // 信息流广告
//        'REWARD_VIDEO' => 'b62ecb800e1f84',  // 激励视频广告
//        'INTERSTITIAL' => 'b62b028b61c800',  // 插屏广告
//        'BANNER' => 'b62b01a36e4572',        // Banner广告
//        'DRAW_VIDEO' => 'b6305efb12d408'     // Braw广告
    ];

    // 穿山甲广告配置
    public $chuanshanjiaConfig = [
        'APP_ID' => '5718785',
        'APP_NAME' => '健康派卡',
        'SPLASH' => '892015291',        // 开屏广告
        'FEED' => '968273037',          // 信息流广告
        'REWARD_VIDEO' => '968273038',  // 激励视频广告
        'INTERSTITIAL' => '968276216',  // 插屏广告
        'BANNER' => '968276215',        // Banner广告
        'DRAW_VIDEO' => '968276217'     // Braw广告
    ];
    // 穿山甲广告配置 GroMore
    public $chuanshanjiaConfigGroMore = [
        'APP_ID' => '5718785',
        'APP_NAME' => '健康派卡',
        'SPLASH' => '103568783',        // 开屏广告
        'FEED' => '103565176',          // 信息流广告
        'REWARD_VIDEO' => '103564347',  // 激励视频广告
        'INTERSTITIAL' => '103563374',  // 插屏广告
        'BANNER' => '103563764',        // Banner广告
        'DRAW_VIDEO' => '103550275'     // Braw广告
    ];
//    public $chuanshanjiaConfigGroMore = [
//        'APP_ID' => '5718785',
//        'APP_NAME' => '健康派卡',
//        'SPLASH' => '103548450',        // 开屏广告
//        'FEED' => '103549886',          // 信息流广告
//        'REWARD_VIDEO' => '103550077',  // 激励视频广告
//        'INTERSTITIAL' => '103553010',  // 插屏广告
//        'BANNER' => '103556108',        // Banner广告
//        'DRAW_VIDEO' => '103550275'     // Braw广告
//    ];
    // 插屏广告配置
    public $interstitialAdConfig = [
        'continuous_times' => 0,    // 插屏广告连续展示次数
        'time_interval'    => 90000,   // 插屏广告展示时间间隔 （秒）
    ];

    // 配置更新时间
    public $updateTime;
    
    // 配置版本号
    public $configVersion = "1.0.0";
    
    public function __construct() {
        $this->updateTime = date('Y-m-d H:i:s');
    }

    /**
     * 获取配置数组
     */
    public function toArray() {
        return [
            'masterSwitch' => $this->masterSwitch,
            'splashAdSwitch' => $this->splashAdSwitch,
            'interstitialAdSwitch' => $this->interstitialAdSwitch,
            'feedAdSwitch' => $this->feedAdSwitch,
            'rewardVideoAdSwitch' => $this->rewardVideoAdSwitch,
            'bannerAdSwitch' => $this->bannerAdSwitch,
            'drawAdSwitch' => $this->drawAdSwitch,
            'splashAdPlatform' => $this->splashAdPlatform,
            'interstitialAdPlatform' => $this->interstitialAdPlatform,
            'feedAdPlatform' => $this->feedAdPlatform,
            'rewardVideoAdPlatform' => $this->rewardVideoAdPlatform,
            'bannerAdPlatform' => $this->bannerAdPlatform,
            'drawAdPlatform' => $this->drawAdPlatform,
            'kuaishouConfig' => $this->kuaishouConfig,
            'chuanshanjiaConfig' => $this->chuanshanjiaConfig,
            'updateTime' => $this->updateTime,
            'configVersion' => $this->configVersion,
            'interstitialAdConfig' => $this->interstitialAdConfig,
        ];
    }

    /**
     * 获取配置数组 - 测试 穿山甲/快手配置
     */
    public function toArrayGroMore() {
        return [
            'masterSwitch' => $this->masterSwitch,
            'splashAdSwitch' => $this->splashAdSwitch,
            'interstitialAdSwitch' => $this->interstitialAdSwitch,
            'feedAdSwitch' => $this->feedAdSwitch,
            'rewardVideoAdSwitch' => $this->rewardVideoAdSwitch,
            'bannerAdSwitch' => $this->bannerAdSwitch,
            'drawAdSwitch' => $this->drawAdSwitch,
            'splashAdPlatform' => $this->splashAdPlatform,
            'interstitialAdPlatform' => $this->interstitialAdPlatform,
            'feedAdPlatform' => $this->feedAdPlatform,
            'rewardVideoAdPlatform' => $this->rewardVideoAdPlatform,
            'bannerAdPlatform' => $this->bannerAdPlatform,
            'drawAdPlatform' => $this->drawAdPlatform,
            'kuaishouConfig' => $this->kuaishouConfig,
            'chuanshanjiaConfig' => $this->chuanshanjiaConfigGroMore,
            'takuConfig' => $this->takuConfigGroMore,
            'updateTime' => $this->updateTime,
            'configVersion' => $this->configVersion,
            'interstitialAdConfig' => $this->interstitialAdConfig,
        ];
    }
}

/**
 * 广告配置管理API
 */
class AdConfigAPI {
    
    private $configFile = 'ad_config.json';
    
    /**
     * 获取广告配置
     */
    public function getAdConfig() {
        try {
            // 使用默认配置
            $defaultConfig = new AdSwitchConfig();
            // 这里切GroMore配置
            $config = $defaultConfig->openGroMore ? $defaultConfig->toArrayGroMore() : $defaultConfig->toArray();

            return [
                'code' => 200,
                'message' => '获取默认广告配置成功',
                'data' => $config,
                'timestamp' => time()
            ];
            
        } catch (Exception $e) {
            return [
                'code' => 500,
                'message' => '获取广告配置失败: ' . $e->getMessage(),
                'data' => null,
                'timestamp' => time()
            ];
        }
    }
}

// 处理请求
try {
    $api = new AdConfigAPI();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            $response = $api->getAdConfig();
            break;
        default:
            $response = [
                'code' => 405,
                'message' => '不支持的请求方法',
                'data' => null,
                'timestamp' => time()
            ];
            break;
    }
    
} catch (Exception $e) {
    $response = [
        'code' => 500,
        'message' => '服务器内部错误: ' . $e->getMessage(),
        'data' => null,
        'timestamp' => time()
    ];
}

// 输出响应
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

/**
API使用示例:

1. 获取广告配置:
   GET http://jiankangpaika.blcwg.com/jkpk/api/ad_config.php

响应格式:
{
    "code": 200,
    "message": "获取广告配置成功",
    "data": {
        "masterSwitch": true,
        "splashAdSwitch": true,
        "interstitialAdSwitch": true,
        "feedAdSwitch": true,
        "rewardVideoAdSwitch": true,
        "bannerAdSwitch": true,
        "splashAdPlatform": "kuaishou",
        "interstitialAdPlatform": "kuaishou",
        "feedAdPlatform": "kuaishou",
        "rewardVideoAdPlatform": "kuaishou",
        "bannerAdPlatform": "kuaishou",
        "kuaishouConfig": {
            "APP_ID": "90009",
            "APP_NAME": "健康派卡",
            "SPLASH": "4000000042",
            "FEED": "4000000079",
            "REWARD_VIDEO": "90009001",
            "INTERSTITIAL": "4000000276",
            "BANNER": "4000001623",
            "DRAW_VIDEO": "4000000020"
        },
        "chuanshanjiaConfig": {
            "APP_ID": "5717321",
            "APP_NAME": "健康派卡",
            "SPLASH": "103539675",
            "FEED": "103540058",
            "REWARD_VIDEO": "103540252",
            "INTERSTITIAL": "103540148",
            "BANNER": "103538189",
            "DRAW_VIDEO": "103538196"
        },
        "takuConfig": {
            "APP_ID": "a687e57e2e68d0",
            "APP_KEY": "af361b82bcdb21b0c88cbf630fd016b97",
            "APP_NAME": "健康派卡",
            "SPLASH": "b687e61873b69a",
            "FEED": "b687e6160a5b18",
            "REWARD_VIDEO": "b687e60efa56a9",
            "INTERSTITIAL": "b687e6100364a8",
            "BANNER": "b687e617160a6e",
            "DRAW_VIDEO": "103550275"
        },
        "updateTime": "2024-01-01 12:00:00",
        "configVersion": "1.0.0",
        "interstitialAdConfig": {
            "continuous_times": 2,
            "time_interval": 60
        }
    },
    "timestamp": 1704067200
}
*/

?>