<?php
namespace wstmart\app\controller;

use wstmart\common\model\OrderRefunds as M;
use wstmart\common\exception\AppException as AE;
use wstmart\common\service\Refund;
use wstmart\app\service\Users as ASUser;
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
 * 订单退款控制器
 */
class Orderrefunds extends Base{
    protected $beforeActionList = [
        'checkAuth' => ['only'=>'refundprocess,refunddefaultdata,refundinfo,revokerefund,moneywhereabouts,insertexpressno,
        selectexpressno'],
    ];
    protected $openAction = [
        'refundprocess',
        'refunddefaultdata',
        'refundinfo',
        'revokerefund',
        'moneywhereabouts',
        'insertexpressno',
        'orderrefund',
        'selectexpressno',
    ];

    /**
	 * 用户申请退款
	 */
	public function refund(){
		$m = new M();
		$userId = (int)model('app/index')->getUserId();
		$rs = $m->refund($userId);
		return json_encode($rs);
	}
	/**
	 * 商家处理是否同意
	 */
	public function shopRefund(){
		$m = new M();
		$rs = $m->shopRefund();
		return json_encode($rs);
	}

	/*
	 *售后退货退款
	 */
	public function refundProcess()
    {
        $refundId = getInput('refundId', 0);
        $type = getInput('type');
        $goodsStatus = getInput('goodsStatus', 1);
        $refundReason = getInput('refundReason');
        $refundTotal = getInput('refundTotal');
        $refundExplain = getInput('refundExplain') ?? '';
        $refundImg = getInput('refundImg') ?? '';
        $refundOrderId = getInput('orderId');
        $refundPhone = getInput('refundPhone');
        if (empty($refundOrderId) || $refundTotal<0 || checkInt($refundReason, false)==false) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        if(!WSTIsPhone($refundPhone)){
            throw AE::factory(AE::COM_MOBILE_ERR);
        }
        $refund = new Refund();
        $refundReturn = $refund->refundFilter($refundId, $type, $goodsStatus, $refundReason, $refundTotal, $refundExplain, $refundPhone, $refundImg, $refundOrderId);
        return $this->shopJson($refundReturn);
    }

    public function refundDefaultData()
    {
        $orderId = getInput('orderId');
        if (empty($orderId)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $refund = new Refund();
        $refundReturn = $refund->refundDefaultData($orderId);
        return $this->shopJson($refundReturn);
    }

    /*
     * 售后详情
     */
    public function refundInfo()
    {
        $refundId = getInput('refundId');
        if (empty($refundId)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $refund = new Refund();
        $userId =  ASUser::getUserByCache()['userId'];
        $info = $refund->getRefundInfo($refundId, $userId);
        return $this->shopJson($info);
    }

    /*
     * 撤销售后
     */
    public function revokeRefund()
    {
        $refundId = getInput('refundId');
        if (empty($refundId)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $refund = new Refund();
        $res = $refund->updateRefundStatus($refundId);
        return $this->shopJson($res);
    }

    /*
     * 钱款去向
     */
    public function moneyWhereAbouts()
    {
        $refundId = getInput('refundId');
        if (empty($refundId)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $refund = new Refund();
        $res = $refund->moneyWhereAbouts($refundId);
        return $this->shopJson($res);
    }

    /*
     * 同意申请
     */
//    public function agreeRefund()
//    {
//        $refundId = getInput('refundId');
//        if (checkInt($refundId, false)==false) {
//            throw AE::factory(AE::COM_PARAMS_ERR);
//        }
//        $refund = new Refund();
//        $res = $refund->agreeRefund($refundId);
//        return $this->shopJson($res);
//    }

    /*
     * 查询快递单号
     */
    public function selectExpressNo()
    {
        $refundId = getInput('refundId');
        if (empty($refundId)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $refund = new Refund();
        $res = $refund->selectExpressNo($refundId);
        return $this->shopJson($res);
    }

    /*
     * 填写快递单号
     */
    public function insertExpressNo()
    {
        $refundId = getInput('refundId');
        $expressName = getInput('expressName');
        $expressNo = getInput('expressNo');
        if (checkInt($refundId, false)==false || empty($expressName) || empty($expressNo)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $refund = new Refund();
        $res = $refund->insertExpressNo($refundId, $expressName, $expressNo);
        return $this->shopJson($res);
    }
}
