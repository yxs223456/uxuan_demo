<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/8/23
 * Time: 15:22
 */

namespace wstmart\admin\controller;

use wstmart\admin\model\GoodsTempAppraises as M;

class Goodstempappraises extends Base
{

    /**
     * 新增评价假数据
     */
    public function index(){
        $goodsTempAppraise = $this->pageQuery();
        $this->assign("goodsTempAppraise",$goodsTempAppraise);
        return $this->fetch("list");
    }

    public function pageQuery()
    {
        $m = new M();
        return WSTGrid($m->lists());
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

    /**
     * 显示隐藏
     */
    public function setAppraisesToggle(){
        $m = new M();
        return $m->setToggle();
    }

    public function toEdit()
    {
        $m = new M();
        $assign = ['data'=>$this->get(),
            'object'=>$m->getById((int)Input('id'))];

        return $this->fetch("edit",$assign);
    }
}