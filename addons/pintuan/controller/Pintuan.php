<?php
namespace addons\pintuan\controller;

use think\addons\Controller;
use addons\pintuan\model\Pintuans as M;
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
 * 拼团插件
 */
class Pintuan extends Controller{
	protected $addonStyle = 'default';
	public function __construct(){
		parent::__construct();
		$m = new M();
        $data = $m->getConf('Pintuan');
        $this->addonStyle = ($data['addonsStyle']=='')?'default':$data['addonsStyle'];
        $this->assign("addonStyle",$this->addonStyle);
        $this->assign("seoPintuanKeywords",$data['seoPintuanKeywords']);
        $this->assign("seoPintuanDesc",$data['seoPintuanDesc']);
		$this->assign("v",WSTConf('CONF.wstVersion')."_".WSTConf('CONF.wstPCStyleId'));
	}

	public function wxPulist(){
		$m = new M();
		$userId = (int)session('WST_USER.userId');
		$user = model("common/Users")->getById($userId);
		if($user['userName']=='')$user['userName']=$user['loginName'];
		$this->assign('user', $user);
		$rs = $m->pulist();
		return $this->fetch($this->addonStyle."/wechat/users/pulist");
	}

	/**
	 * 在线支付方式
	 */
	public function payTypes(){
		//获取支付方式
		$payments = model('common/payments')->getByGroup('3',1);
        $this->assign('payments',$payments);
		$this->assign('orderNo',input("get.orderNo"));
		return $this->fetch($this->addonStyle."/wechat/index/pay_list");
	}

	/**
     * 微信拼团列表页
     */
    public function pageQuery(){
    	$m = new M();
    	$rs = $m->pulist();
    	return $rs;
    }
    /**
     * 取消拼单
     */
    public function toCancel(){
    	$m = new M();
    	$rs = $m->delTuan();
    	return $rs;
    }
    
    /**
     * 拼单退款
     */
    public function tuanRefund(){
    	$m = new M();
    	$rs = $m->tuanRefund();
        $m->batchRefund();
    	echo json_encode($rs);
    }
}