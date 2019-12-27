<?php
namespace addons\kuaidi\model;
use think\addons\BaseModel as Base;
use think\Db;
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
 * 快递查询业务处理
 */
class Kuaidi extends Base{
	
	/**
	 * 绑定勾子
	 */
	public function install(){
		Db::startTrans();
		try{
			$hooks = array("adminDocumentOrderView","homeDocumentOrderView","afterQueryUserOrders","mobileDocumentOrderList","wechatDocumentOrderList");
			$this->bindHoods("Kuaidi", $hooks);
			
			Db::commit();
			return true;
		}catch (\Exception $e) {
			Db::rollback();
			return false;
		}
	}
	
	/**
	 * 解绑勾子
	 */
	public function uninstall(){
		Db::startTrans();
		try{
			$hooks = array("adminDocumentOrderView","homeDocumentOrderView","afterQueryUserOrders","mobileDocumentOrderList","wechatDocumentOrderList");
			$this->unbindHoods("Kuaidi", $hooks);
			
			Db::commit();
			return true;
		}catch (\Exception $e) {
			Db::rollback();
			return false;
		}
	}
	
	public function getExpress($orderId){
		$conf = $this->getConf("Kuaidi");
		$express = Db::name('orders')->where(["orderId"=>$orderId])->field(['expressId','expressNo'])->find();
		return $express;
	}
	
	public function getOrderExpress($orderId){
		$conf = $this->getConf("Kuaidi");
		$express = Db::name('orders')->where(["orderId"=>$orderId])->field(['expressId','expressNo'])->find();
		
		if($express["expressId"]>0){
			$expressId = $express["expressId"];
			$row = Db::name('express')->where(["expressId"=>$expressId])->find();
			$typeCom =  strtolower($row["expressCode"]); //快递公司
			$typeNu = $express["expressNo"]; //快递单号
			
			$appKey= $conf["kuaidiKey"];
			
			$expressLogs = null;
			$companys = array('ems','shentong','yuantong','shunfeng','yunda','tiantian','zhongtong','zengyisudi');
			if(in_array($typeCom,$companys)){
				$url = 'http://www.kuaidi100.com/query?type=' . $typeCom . '&postid=' . $typeNu;
			}else{
				$url ='http://api.kuaidi100.com/api?id='.$appKey.'&com='.$typeCom.'&nu='.$typeNu.'&show=0&muti=1&order=asc';
			}
			$expressLogs = $this -> curl($url);
			return $expressLogs;
		}
		
	}
	
	public function getOrderInfo(){
		$data = array();
		$orderId = input("orderId");
		$data["express"] = Db::name('orders o')->join('__EXPRESS__ e', 'o.expressId=e.expressId')->where(["orderId"=>$orderId])->field(['e.expressId','expressNo','expressName'])->find();
		$data["goodlist"] = Db::name('orders o')->join('__ORDER_GOODS__ og','o.orderId=og.orderId')->where(["o.orderId"=>$orderId])->field(["goodsId","goodsImg"])->limit(1)->select();
		return $data;
	}
	
	public function curl($url) {
		$curl = curl_init();
		curl_setopt ($curl, CURLOPT_URL, $url);
		curl_setopt ($curl, CURLOPT_HEADER,0);
		curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($curl, CURLOPT_USERAGENT,$_SERVER['HTTP_USER_AGENT']);
		curl_setopt ($curl, CURLOPT_TIMEOUT,5);
		$content = curl_exec($curl);
		curl_close ($curl);
		return $content;
	}
	

	public  function getOrderDeliver($orderId){
		$rs = Db::name('orders o')->where(["orderId"=>$orderId])->field("deliverType,orderStatus,expressNo")->find();
		return $rs;
	}
	
	
	
}
