<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/9/6
 * Time: 11:00
 */

namespace wstmart\admin\controller;

use wstmart\admin\model\AuditSwitchs as M;

class Auditswitchs extends Base
{
    /**
     * 新增评价假数据
     */
    public function index(){
        $auditswitch = $this->pageQuery();
        $this->assign("auditswitchs",$auditswitch);
        return $this->fetch("list");
    }

    public function pageQuery()
    {
        $m = new M();
        $arr = WSTGrid($m->pageQuery());
        $channelType = config('web.auditChannel');
        foreach ($arr['items'] as $k=>$v) {
            $arr['items'][$k]['channel'] = $channelType[$v['channel']];
        }
        return $arr;
    }

    public function get()
    {
        $id = input('id/d');
        $m = new M();
        return $m->getById($id);
    }

    public function add()
    {
        $post = input('post.');
        $m = new M();
        return $m->add($post);
    }

    public function edit()
    {
        $post = input('post.');
        $m = new M();
        return $m->edit($post);
    }

    /**
     * 删除
     */
    public function del(){
        $m = new M();
        $id = input('post.id/d');
        return $m->del($id);
    }

    public function toEdit()
    {
        $m = new M();
        $assign = ['data'=>$this->get(),
            'object'=>$m->getById((int)Input('id'))];
        return $this->fetch("edit",$assign);
    }
}