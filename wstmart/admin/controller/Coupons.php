<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/9/11
 * Time: 16:03
 */

namespace wstmart\admin\controller;

use wstmart\admin\model\Coupons as C;
use addons\coupon\model\Coupons as M;

class Coupons extends Base
{
    /**
     * 红包列表
     */
    public function index(){
        $couponList = $this->pageQuery(1);
        $this->assign("couponList",$couponList);
        return $this->fetch("list");
    }

    /**
     * 获取分页
     */
    public function pageQuery(){
        $m = new C();
        $list = WSTGrid($m->pageQuery());
        foreach ($list['items'] as $k=>$item) {
            $list['items'][$k]['useCondition'] = $item['useCondition']==0 ? '无条件' : '满'.$item['useMoney'].'可用';
            switch ($item['useObjects']) {
                case 0:
                    $name = '全部商品';
                    break;
                case 1:
                    $name = '指定商品';
                    break;
            }
            $list['items'][$k]['useObjects'] = $name ?? '';
            $list['items'][$k]['grantObjects'] = $item['grantObjects']==0 ? '全部用户' : '指定用户';
        }
        return $list;
    }

    public function get()
    {
        $guideId = input('couponId/d');
        $m = new C();
        return $m->getById($guideId);
    }

    public function add()
    {
        $post = input('post.');
        $m = new C();
        return $m->add($post);
    }

    public function edit()
    {
        $post = input('post.');
        $m = new C();
        return $m->edit($post);
    }

    public function toEdit()
    {
        $m = new C();
        $assign = ['data'=>$this->get(),
            'object'=>$m->getById((int)Input('couponId'))
        ];
        $assign['object']['goods'] = $m->getGoodsInfoById((int)Input('couponId'));
        return $this->fetch("edit",$assign);
    }

    public function del()
    {
        $m = new C();
        $id = input('post.couponId/d');
        return $m->del($id);
    }

    /**
     * 查询商品
     */
//    public function searchGoods(){
//        $m = new M();
//        $shopId = input('shopId', 1);
//        return $m->searchGoodsByAdmin($shopId);
//    }

    /******************************兑换红包**********************************/

    /**
     * 红包列表
     */
    public function exchangeIndex(){
        $exchangeList = $this->exchangePageQuery(3);
        $this->assign("exchangeList",$exchangeList);
        return $this->fetch("exchangeList");
    }

    /**
     * 获取分页
     */
    public function exchangePageQuery(){
        $m = new C();
        $list = WSTGrid($m->pageQuery(3));
        foreach ($list['items'] as $k=>$item) {
            $list['items'][$k]['useCondition'] = $item['useCondition']==0 ? '无条件' : '满'.$item['useMoney'].'可用';
            switch ($item['useObjects']) {
                case 0:
                    $name = '全部商品';
                    break;
                case 1:
                    $name = '指定商品';
                    break;
            }
            $list['items'][$k]['useObjects'] = $name ?? '';
            $list['items'][$k]['grantObjects'] = $item['grantObjects']==0 ? '全部用户' : '指定用户';
        }
        return $list;
    }

    public function exchangeAdd()
    {
        $post = input('post.');
        $m = new C();
        return $m->exchangeAdd($post);
    }

    public function exchangeEdit()
    {
        $post = input('post.');
        $m = new C();
        return $m->exchangeEdit($post);
    }

    public function exchangeToEdit()
    {
        $m = new C();
        $assign = ['data'=>$this->get(),
            'object'=>$m->getById((int)Input('couponId'))
        ];
        $assign['object']['goods'] = $m->getGoodsInfoById((int)Input('couponId'));
        return $this->fetch("exchangeEdit",$assign);
    }

    public function exchangeDel()
    {
        $m = new C();
        $id = input('post.couponId/d');
        return $m->del($id);
    }
}