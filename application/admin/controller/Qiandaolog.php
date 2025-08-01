<?php

namespace app\admin\controller;

use app\common\controller\Backend;

/**
 * 签到记录管理
 *
 * Class Qiandaolog
 * @author LEE
 * @package app\admin\controller
 * @Date 2025-07-05 09:44
 */
class Qiandaolog extends Backend
{

    /**
     * Qiandaolog模型对象
     * @var \app\admin\model\Qiandaolog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Qiandaolog;

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
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $total = $this->model
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with('user')
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
