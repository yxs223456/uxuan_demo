<?php
namespace wstmart\app\controller;
use wstmart\common\model\Orders as OM;
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
 * 余额控制器
 */
class Wallets extends Base{
	// 前置方法执行列表
	protected $beforeActionList = [
			'checkAuth'
	];
	/**
	 * 跳去支付页面
	 */
	public function payment(){
        $data = [];
        $data['orderNo'] = input('orderNo');
        $data['isBatch'] = (int)input('isBatch');
        $data['userId'] = model('index')->getUserId();
        //$this->assign('data',$data); //订单号、用户id、isBatch
		$m = new OM();
		$rs = $m->getOrderPayInfo($data);// needPay、payRand
		// 订单信息
		$list = $m->getByUnique($data['userId']);// 根据订单唯一流水号 获取订单信息

		// 删除无用字段
		unset($list['payments']);
		
		if(empty($rs)){
			return json_encode(WSTReturn('订单已支付',-1));
			// 判断获取的需要支付信息为空，则说明已支付.跳转订单列表
			$this->assign('type','');
		}else{
			$this->assign('needPay',$rs['needPay']);
			//获取用户钱包
			$user = model('users')->getFieldsById($data['userId'],'userMoney');
			$list['userMoney'] = $user['userMoney'];// 用户钱包可用余额
	    }
	    // 域名,用于显示图片
	    $list['domain'] = $this->domain();
	    return json_encode(WSTReturn('请求成功', 1, $list));die;
	}
	/**
	 * 钱包支付
	 */
	public function payByWallet(){
		$m = new OM();
		$userId = (int)model('index')->getUserId();
		return json_encode($m->payByWallet($userId));
	}
}
