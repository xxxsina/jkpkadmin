<?php

/**
 * 通知消息
 * @return string
 * @author LEE
 * @Date 2025-07-17 12:46
 */
function getNoticeMessage()
{
    $noticeMessage = <<<EOF
<span style="color: red; font-weight: bold; font-size: 14px; border: 1px red solid;">通知：APP内不要有任何下载、注册、付款等行为！</span>
1、如果签到点不动，请在页面上等待5秒左右再点签到。或者在："我的"页面里，点退出APP然后再重新打开软件签到。
2、提交完表单联系客服后，如果长时间未回复，可在"我的"点击退出APP，再打软件刷新回复结果。
EOF;

    return $noticeMessage;
}

/**
 * 偷签到的第几次
 * @return int[]
 * @author LEE
 * @Date 2025-07-17 21:55
 */
function getThiefArr()
{
    return [];
}