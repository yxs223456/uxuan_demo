<?php
namespace addons\bargain\controller;

use think\addons\Controller;
use addons\bargain\model\Bargains as M;
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
 * 全民砍价插件
 */
class Users extends Controller{
	/**
	 * 微信我的砍价页
	 */
	public function wxbargain(){
		return $this->fetch("/wechat/users/list");
	}
	/**
	 * 加载砍价数据
	 */
	public function pageQuery(){
		$m = new M();
		return $m->pageQueryByUser();
	}
}