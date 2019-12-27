<?php
namespace addons\auction\controller;

use think\addons\Controller;
use addons\auction\model\Auctions as M;
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
 * 拍卖活动插件
 */
class Shops extends Controller{
	/**
	 * 拍卖列表
	 */
	public function auction(){
    	return $this->fetch("/home/shops/list");
	}
	/**
	 * 加载拍卖数据
	 */
	public function pageQuery(){
		$m = new M();
		return $m->pageQueryByShop();
	}

	/**
	 * 搜索商品
	 */
	public function searchGoods(){
		$m = new M();
		return $m->searchGoods();
	}

	/**
	 * 跳去编辑页面
	 */
	public function edit(){
		$id = (int)input('id');
		$object = [];
		$m = new M();
		if($id>0){
            $object = $m->getById($id);
		}else{
			$object = $m->getEModel('auctions');
			$object['marketPrice'] = '';
			$object['goodsName'] = '请选择拍卖商品';
			$object['startTime'] = date('Y-m-d H:00:00',strtotime("+2 hours"));
			$object['endTime'] = date('Y-m-d H:00:00',strtotime("+1 month"));
		}
		$this->assign("object",$object);
		return $this->fetch("/home/shops/edit");
	}

	/**
	 * 保存拍卖信息
	 */
	public function toEdit(){
		$id = (int)input('post.auctionId');
		$m = new M();
		if($id==0){
            return $m->add();
		}else{
            return $m->edit();
		}
	}

	/**
	 * 删除拍卖
	 */
	public function del(){
		$m = new M();
		return $m->del();
	}

	/**
	 * 查看拍卖参与者列表
	 */
    public function bidding(){
    	$this->assign("id",(int)input('id'));
    	return $this->fetch("/home/shops/list_bids");
    }
    /**
     * 查询拍卖参与者列表
     */ 
    public function pageAuctionLogQueryByShops(){
    	$m = new M();
		return $m->pageAuctionLogQuery((int)input('id'));
    }
}