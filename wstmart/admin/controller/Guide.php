<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/8/9
 * Time: 18:49
 */

namespace wstmart\admin\controller;

use wstmart\admin\model\Guides as G;

class Guide extends Base
{
    /**
     * 订单列表
     */
    public function index(){
        $guideList = $this->pageQuery();
        $this->assign("guideList",$guideList);
        return $this->fetch("list");
    }

    /**
     * 获取分页
     */
    public function pageQuery(){
        $m = new G();
        $list = WSTGrid($m->pageQuery());
        $targetChannel = config('web.targetChannel');
        $guideType = config('web.guideType');
        foreach ($list['items'] as $k=>$item) {
            $list['items'][$k]['type'] = $guideType[$item['type']];
            $list['items'][$k]['accessType'] = $item['accessType']==0 ? 'h5' : '应用内页面';
            $list['items'][$k]['proportion'] = $item['proportion']==0 ? '全屏' : '3/4屏';
            $list['items'][$k]['targetChannel'] = $targetChannel[$item['targetChannel']];

        }
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
        $id = input('post.guideId/d');
        return $m->del($id);
    }
}