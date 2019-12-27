<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/8/31
 * Time: 10:19
 */

namespace wstmart\admin\controller;

use wstmart\admin\model\VersionUps as V;

class Versionups extends Base
{
    /**
     *
     */
    public function index(){
        $versionupList = $this->pageQuery();
        $this->assign("versionupsList",$versionupList);
        return $this->fetch("list");
    }

    /**
     * 获取分页
     */
    public function pageQuery(){
        $m = new V();
        $list = WSTGrid($m->pageQuery());
        foreach ($list['items'] as $k=>$item) {
            $list['items'][$k]['type'] = $item['type']==0 ? 'android' : 'ios';
            $list['items'][$k]['upgradeType'] = $item['upgradeType']==0 ? '普通' : '强制';
        }
        return $list;
    }

    public function add()
    {
        $post = input('post.');
        $m = new V();
        return $m->add($post);
    }

    public function edit()
    {
        $post = input('post.');
        $m = new V();
        return $m->edit($post);
    }

    public function toEdit()
    {
        $m = new V();
        $assign = ['data'=>$this->get(),
            'object'=>$m->getById((int)Input('id'))];

        return $this->fetch("edit",$assign);
    }

    public function get()
    {
        $id = input('id/d');
        $m = new V();
        return $m->getById($id);
    }

    public function del()
    {
        $id = input('id/d');
        $m = new V();
        return $m->del($id);
    }
}