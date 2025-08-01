<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use Exception;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 客户问题管理
 *
 * @icon fa fa-circle-o
 */
class Customermessage extends Backend
{

    /**
     * Customermessage模型对象
     * @var \app\admin\model\Customermessage
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Customermessage;

    }

    public function index($ids = null)
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) =
                $this->buildparams(['u.username', 'realname', 'is_overcome'], true);

            $total = $this->model
                ->alias('customermessage')
                ->join('user u', 'customermessage.user_id = u.id', 'LEFT')
                ->field('customermessage.*, u.username')
                ->where($where)
                ->count();

            $list = $this->model
                ->alias('customermessage')
                ->join('user u', 'customermessage.user_id = u.id', 'LEFT')
                ->field('customermessage.*, u.username')
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();

            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }

        return $this->view->fetch();
    }
}
