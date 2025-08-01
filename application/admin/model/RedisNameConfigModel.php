<?php

namespace app\admin\model;

use think\Model;

class RedisNameConfigModel extends Model
{
    protected $prefix = 'jkpk';
    // 用户问题列表
    public function getCustomerMessagesListKey($userId)
    {
        return sprintf("%s:customer_messages:user:%s", $this->prefix, $userId);
    }
    // 用户问题
    public function getCustomerMessagesKey($userId, $msgId)
    {
        return sprintf("%s:customer_messages:userId:%s:msgId:%s", $this->prefix, $userId, $msgId);
    }

    public function getArticleById($id)
    {
        return sprintf("%s:article:id:%s", $this->prefix, $id);
    }

    public function getArticleList()
    {
        return sprintf("%s:articles:list", $this->prefix);
    }
}
