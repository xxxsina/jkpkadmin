<?php

namespace app\admin\controller;

use app\common\controller\Backend;

/**
 * 签到记录管理
 *
 * Class ScoreCalendar
 * @author LEE
 * @package app\admin\controller
 * @Date 2025-07-05 09:44
 */
class Scorecalendar extends Backend
{

    /**
     * ScoreCalendar模型对象
     * @var \app\admin\model\ScoreCalendar
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\ScoreCalendar;

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
                $this->buildparams(
                    [
                        'u.username',
                        'score_calendar.date_stamp',
                        'score_calendar.createtime',
                        'score_calendar.type'
                    ],
                    true);

            $total = $this->model
                ->alias('score_calendar')
                ->join('user u', 'score_calendar.user_id = u.id', 'LEFT')
                ->field('score_calendar.*, u.username')
                ->where($where)
                ->count();

            $list = $this->model
                ->alias('score_calendar')
                ->join('user u', 'score_calendar.user_id = u.id', 'LEFT')
                ->field('score_calendar.*, u.username')
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
