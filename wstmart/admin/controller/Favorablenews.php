<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/10/11
 * Time: 18:12
 */

namespace wstmart\admin\controller;

use wstmart\admin\model\FavorableNews as F;
use wstmart\admin\model\Staffs;
class Favorablenews extends Base
{
    public function index(){
        $newList = $this->pageQuery();
        $this->assign("newList",$newList);
        return $this->fetch("list");
    }

    /**
     * 获取分页
     */
    public function pageQuery(){
        $m = new F();
        $list = WSTGrid($m->pageQuery());
        foreach ($list['items'] as $k=>$item) {
            $list['items'][$k]['accessType'] = $item['accessType']==0 ? 'h5' : '应用内页面';
            $list['items'][$k]['sendStatus'] = $item['sendStatus']==0 ? '未发送' : '已发送';
            $list['items'][$k]['title'] = mb_substr($item['title'], 0, 8).'...';
            $list['items'][$k]['text'] = mb_substr($item['text'], 0, 10).'...';
            $list['items'][$k]['adminId'] = (new Staffs())->getLoginNameByStaffId($item['adminId']);
        }
        return $list;
    }

    public function get()
    {
        $guideId = input('id/d');
        $m = new F();
        return $m->getById($guideId);
    }

    public function add()
    {
        $post = input('post.');
        $m = new F();
        return $m->add($post);
    }

    public function edit()
    {
        $post = input('post.');
        $m = new F();
        return $m->edit($post);
    }

    public function toEdit()
    {
        $m = new F();
        $assign = ['data'=>$this->get(),
            'object'=>$m->getById((int)Input('id'))];
        return $this->fetch("edit",$assign);
    }

    public function del()
    {
        $m = new F();
        $id = input('post.id/d');
        return $m->del($id);
    }

}