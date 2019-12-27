<?php
namespace addons\kuaidi\controller;

use think\addons\Controller;
use addons\kuaidi\model\Kuaidi as M;
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
 * 快递查询控制器
 */
class Kuaidi extends Controller{
	public function __construct(){
		parent::__construct();
		$this->assign("v",WSTConf('CONF.wstVersion')."_".WSTConf('CONF.wsthomeStyleId'));
	}
    
	/**
	 * 跳转订单详情【mobile】
	 */
	public function checkMobileExpress(){
		$m = new M();
		$rs = $m->getOrderExpress(input("orderId"));
		$express = json_decode($rs, true);
		$state = isset($express["state"])?$express["state"]:'-1';
		$data = $m->getOrderInfo();
		$data["express"]["stateTxt"] = $this->getExpressState($state);
		$express["express"] = $data["express"];
		$express["goodlist"] = $data["goodlist"];
		return $express;
	}
	
	/**
	 * 跳转订单详情【wechat】
	 */
	public function checkWechatExpress(){
		$m = new M();
		$rs = $m->getOrderExpress(input("orderId"));
		$express = json_decode($rs, true);
		$state = isset($express["state"])?$express["state"]:'-1';
		$data = $m->getOrderInfo();
		$data["express"]["stateTxt"] = $this->getExpressState($state);
		$express["express"] = $data["express"];
		$express["goodlist"] = $data["goodlist"];
		return $express;
	}
	
	public function getExpressState($state){
		$stateTxt = "";
		switch ($state) {
			case '0':$stateTxt="运输中";break;
			case '1':$stateTxt="揽件";break;
			case '2':$stateTxt="疑难";break;
			case '3':$stateTxt="收件人已签收";break;
			case '4':$stateTxt="已退签";break;
			case '5':$stateTxt="派件中";break;
			case '6':$stateTxt="退回";break;
			default:$stateTxt="暂未获取到状态";break;
		}
		return $stateTxt;
	}
}