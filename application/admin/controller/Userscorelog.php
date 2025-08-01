<?php

namespace app\admin\controller;

use app\common\controller\Backend;

/**
 * 积分记录管理
 *
 * @icon fa fa-circle-o
 */
class Userscorelog extends Backend
{

    /**
     * Userscorelog模型对象
     * @var \app\admin\model\Userscorelog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Userscorelog;

    }

    public function index($ids = null)
    {
        $jump_id = $ids;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) =
                $this->buildparams('u.username');

            $total = $this->model
                ->alias('log')
                ->join('user u', 'log.user_id = u.id', 'LEFT')
                ->field('log.*, u.username')
                ->where($where)
                ->count();

            $list = $this->model
                ->alias('log')
                ->join('user u', 'log.user_id = u.id', 'LEFT')
                ->field('log.*, u.username')
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();

            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }

        $this->view->assign('jump_id', $jump_id);
        return $this->view->fetch();
    }
}
