<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/9/21
 * Time: 10:11
 */

namespace wstmart\admin\controller;

use wstmart\admin\model\GoodsTags as G;

class Goodstags extends Base
{
    /**
     * 商品标签列表
     */
    public function index(){
        $goodsTagsList = $this->pageQuery();
        $this->assign("goodsTagsList",$goodsTagsList);
        return $this->fetch("list");
    }

    /**
     * 获取分页
     */
    public function pageQuery(){
        $m = new G();
        $list = WSTGrid($m->pageQuery());
        return $list;
    }

    /**
     * 显示隐藏
     */
    public function setToggle(){
        $m = new G();
        return $m->setToggle();
    }

    public function get()
    {
        $guideId = input('id/d');
        $m = new G();
        return $m->getById($guideId);
    }

    public function add()
    {
        $post = input('post.');
        $m = new G();
        return $m->add($post);
    }

    public function edit()
    {
        $post = input('post.');
        $m = new G();
        return $m->edit($post);
    }

    public function toEdit()
    {
        $m = new G();
        $assign = ['data'=>$this->get(),
            'object'=>$m->getById((int)Input('id'))];
        return $this->fetch("edit",$assign);
    }

    public function del()
    {
        $m = new G();
        $id = input('post.id/d');
        return $m->del($id);
    }
}