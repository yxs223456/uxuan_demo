<?php
namespace addons\coupon\controller;
use think\addons\Controller;
use addons\coupon\model\Coupons as M;
use think\Db;
use wstmart\common\exception\AppException as AE;
use wstmart\app\service\Users as ASUsers;
/**
 * ============================================================================
 * WSTMart多用户商城
 * 版权所有 2016-2066 广州商淘信息科技有限公司，并保留所有权利。
 * 官网地址:http://www.wstmart.net
 * 交流社区:http://bbs.shangtao.net
 * 联系QQ:153289970
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！未经本公司授权您只能在不用于商业目的的前提下对程序代码进行修改和使用；
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * 插件控制器
 */
class Apis extends Controller{
    // 前置方法执行列表
    // 前置方法执行列表
    protected $beforeActionList = [
        'checkAuth'  =>  ['except'=>'pagecouponquery'],// 只要访问only下的方法才才需要执行前置操作
    ];
    /**
    * APP请求检测是否有安装插件
    */
    public function index(){
        return json_encode(['status'=>1]);
    }
    /**
    * 获取用户优惠券数量
    */
    public function getUserCouponNum(){
        $m = new M();
        $userId = model('app/index')->getUserId();
        $num = $m->couponsNum($userId);
        return json(WSTReturn('success', 1, $num));
    }
     // 权限验证方法
//    protected function checkAuth(){
//        $tokenId = input('tokenId');
//        if($tokenId==''){
//            $rs = json_encode(WSTReturn('您还未登录',-999));
//            die($rs);
//        }
//        $userId = Db::name('app_session')->where("tokenId='{$tokenId}'")->value('userId');
//        if(empty($userId)){
//            $rs = json_encode(WSTReturn('登录信息已过期,请重新登录',-999));
//            die($rs);
//        }
//        return true;
//    }

    // 权限验证方法
//    protected function checkAuth(){
//        $tokenId = isset($_SERVER['HTTP_TOKEN']) ? $_SERVER['HTTP_TOKEN'] : '';
//        if($tokenId==''){
//            $rs = json_encode(WSTReturn('您还未登录',-999));
//            die($rs);
//        }
//        $userId = Db::name('app_session')->where("tokenId='{$tokenId}'")->value('userId');
//        if(empty($userId)){
//            $rs = json_encode(WSTReturn('登录信息已过期,请重新登录',-999));
//            die($rs);
//        }
//        return $userId;
//    }
    /*
    * 领券中心列表查询(原版)
    */
//    public function pageCouponQuery(){
//        $m = new M();
//        $userId = model('app/index')->getUserId();
//        $rs = $m->pageCouponQuery($userId);
//        return json_encode(WSTReturn('ok',1,$rs));
//    }
    /*
    * 领券中心列表查询
    */
    public function pageCouponQuery(){
        $offset = getInput('offset', 1);
        $pagesize = getInput('pagesize');
        $pageSize = getInput('pageSize');
        $page = $pagesize ?? $pageSize;
        if (empty($page)) {
            $page = 5;
        }
        $m = new M();
        $userId = ASUsers::getUserByCache()['userId'] ?? 0;
        $rs = $m->pageCouponQuery($userId, 0, $offset, $page);
        return json(WSTReturn('success', 1, $rs));
    }
	/**
    * 领取优惠券（原版）
    */
//    public function receive(){
//        $this->checkAuth();
//        $m = new M();
//        $userId = model('app/index')->getUserId();
//        $rs = $m->receive($userId);
//        return json_encode($rs);
//    }

    /**
     * 领取优惠券
     */
    public function receive(){
        $userId = ASUsers::getUserByCache()['userId'];
        $couponId = getInput('couponId/d');
        if (checkInt($couponId, false)=== false) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $m = new M();
        $rs = $m->receive($userId, $couponId);
        return json(WSTReturn('success', 1, $rs));
    }

    /**
     * 兑换优惠券列表
     */
    public function exchangeCouponsList(){
        $userId = ASUsers::getUserByCache()['userId'];
        $offset = (int)getInput('offset', 1);
        $pagesize = getInput('pagesize');
        $pageSize = getInput('pageSize');
        $page = $pagesize ?? $pageSize;
        if (empty($page)) {
            $page = 5;
        }
        $m = new M();
        $rs = $m->exchangeCouponslist($userId, $offset, $page);
        return json(WSTReturn('success', 1, $rs));
    }

    /**
     * 兑换优惠券
     */
    public function exchangeCoupons(){
        $userId = ASUsers::getUserByCache()['userId'];
        $m = new M();
        $couponId = getInput('couponId');
        if (checkInt($couponId, false)=== false) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $rs = $m->exchangeCoupons($userId, $couponId);
        return json($rs);
    }
    /**
     * 兑换记录
     */
    public function exchangeRecord(){
        $userId = ASUsers::getUserByCache()['userId'];
        $m = new M();
        $rs = $m->exchangeRecord($userId);
        return json(WSTReturn($rs));
    }
    /*
     * 获取指定商品的优惠券
     */
    public function getCouponsByGoods(){
        $m = new M();
        $userId = ASUsers::getUserByCache()['userId'];
        $goodsId = getInput('goodsId/d');
        $count = getInput('count/d');
        $isPintuan = getInput('isPintuan');
        $tuanId = getInput('tuanId', 0);
        $specId = getInput('specId', 0);
        $offset = getInput('offset', 1);
        $pagesize = getInput('pagesize');
        $pageSize = getInput('pageSize');
        $page = $pagesize ?? $pageSize;
        if (empty($page)) {
            $page = 10;
        }
        if (empty($goodsId) || checkInt($isPintuan)==false || checkInt($count)==false) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $rs = $m->getCouponsByGoods($userId, $goodsId, $count, $isPintuan, $tuanId, $specId, $offset, $page);
        return json(WSTReturn('success', 1, $rs));
    }
    /**
     * 加载用户优惠券数据
     */
    public function pageQueryByUser(){
        $userId = ASUsers::getUserByCache()['userId'];
        $offset = (int)getInput('offset', 1);
        $pagesize = getInput('pagesize');
        $pageSize = getInput('pageSize');
        $page = $pagesize ?? $pageSize;
        if (empty($page)) {
            $page = 5;
        }
        $status = (int)getInput('status/d', 0);
        if (checkInt($status)==false) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $m = new M();
        $rs = $m->pageQueryByUser($userId, $status, $offset, $page);
        return json(WSTReturn('success', 1, $rs));
    }


    /**
    *  可用优惠券商品查询
    *  @condition 排序条件
    *  @desc  
    *  @couponId  优惠券id
    */
    public function pageQueryByCouponGoods(){
        $m = new M();
        $rs = $m->pageQueryByCouponGoods();
        $rs['domain'] = url('/','','',true);
        return json(WSTReturn('success', 1, $rs));
    }
}