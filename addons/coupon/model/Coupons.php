<?php
namespace addons\coupon\model;

use think\addons\BaseModel as Base;
use addons\coupon\validate\Coupons as Validate;
use think\Db;
use think\Loader;
use wstmart\common\exception\AppException as AE;
use wstmart\common\model\Goods;
use wstmart\app\service\Orders as O;
use wstmart\common\model\Users;
use wstmart\common\service\News;
use wstmart\common\helper\Redis;


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
 * 优惠券接口
 */
class Coupons extends Base{
	protected $pk = 'couponId';
	/***
     * 安装插件
     */
    public function installMenu(){
    	Db::startTrans();
		try{
			$hooks = ['homeDocumentGoodsPropDetail','mobileDocumentGoodsPropDetail','wechatDocumentGoodsPropDetail',
			          'afterQueryGoods','afterQueryShops','afterQueryCarts','afterCalculateCartMoney','beforeInsertOrder','homeDocumentCartShopPromotion',
			          'mobileDocumentCartShopPromotion','wechatDocumentCartShopPromotion','adminDocumentOrderSummaryView','homeDocumentOrderSummaryView',
			          'mobileDocumentOrderSummaryView','wechatDocumentOrderSummaryView','mobileDocumentUserIndexTools','wechatDocumentUserIndexTools',
			          'homeDocumentSettlementShopSummary','mobileDocumentUserIndexTerm','wechatDocumentUserIndexTerm'];
			$this->bindHoods("Coupon", $hooks);
			$now = date("Y-m-d H:i:s");
			//用户中心
			Db::name('home_menus')->insert(["parentId"=>10,"menuName"=>"我的优惠券","menuUrl"=>"addon/coupon-users-index","menuOtherUrl"=>"addon/coupon-users-pageQuery","menuType"=>0,"isShow"=>1,"menuSort"=>3,"dataFlag"=>1,"createTime"=>$now,"menuMark"=>"coupon"]);
			//商家中心
			Db::name('home_menus')->insert(["parentId"=>77,"menuName"=>"优惠券","menuUrl"=>"addon/coupon-shops-index","menuOtherUrl"=>"addon/coupon-shops-edit,addon/coupon-shops-pageQuery,addon/coupons-shops-toEdit,addon/coupon-shops-del","menuType"=>1,"isShow"=>1,"menuSort"=>1,"dataFlag"=>1,"createTime"=>$now,"menuMark"=>"coupon"]);
			$this->addMobileBtn();
			installSql("coupon");
			Db::commit();
			return true;
		}catch (\Exception $e) {
	 		Db::rollback();
	  		return false;
	   	}
    }

    /**
	 * 删除菜单
	 */
	public function uninstallMenu(){
		Db::startTrans();
		try{
			$hooks = ['homeDocumentGoodsPropDetail','mobileDocumentGoodsPropDetail','wechatDocumentGoodsPropDetail',
			          'afterQueryGoods','afterQueryShops','afterQueryCarts','afterCalculateCartMoney','beforeInsertOrder','homeDocumentCartShopPromotion',
			          'mobileDocumentCartShopPromotion','wechatDocumentCartShopPromotion','adminDocumentOrderSummaryView','homeDocumentOrderSummaryView',
			          'mobileDocumentOrderSummaryView','wechatDocumentOrderSummaryView','mobileDocumentUserIndexTools','wechatDocumentUserIndexTools',
			          'homeDocumentSettlementShopSummary','mobileDocumentUserIndexTerm','wechatDocumentUserIndexTerm'];
			$this->unbindHoods("Coupon", $hooks);
			Db::name('home_menus')->where(["menuMark"=>"coupon"])->delete();
			uninstallSql("coupon");//传入插件名
			$this->delMobileBtn();
			Db::commit();
			return true;
		}catch (\Exception $e) {
	 		Db::rollback();
	  		return false;
	   	}
	}

	/**
	 * 菜单显示隐藏
	 */
	public function toggleShow($isShow = 1){
		Db::startTrans();
		try{
			Db::name('home_menus')->where(["menuMark"=>"coupon"])->update(["isShow"=>$isShow]);
			Db::name('navs')->where(["navUrl"=>"addon/coupon-coupons-index.html"])->update(["isShow"=>$isShow]);
			if($isShow==1){
				$this->addMobileBtn();
			}else{
				$this->delMobileBtn();
			}
			Db::commit();
			return true;
		}catch (\Exception $e) {
	 		Db::rollback();
	  		return false;
	   	}
	}
	
	public function addMobileBtn(){
		$data = array();
		$data["btnName"] = "领券中心";
		$data["btnSrc"] = 0;
		$data["btnUrl"] = "/addon/coupon-coupons-moindex";
		$data["btnImg"] = "addons/coupon/view/mobile/index/img/coupon.png";
		$data["addonsName"] = "Coupon";
		$data["btnSort"] = 6;
		Db::name('mobile_btns')->insert($data);
		$data = array();
		$data["btnName"] = "领券中心";
		$data["btnSrc"] = 1;
		$data["btnUrl"] = "/addon/coupon-coupons-wxindex";
		$data["btnImg"] = "addons/coupon/view/wechat/index/img/coupon.png";
		$data["addonsName"] = "Coupon";
		$data["btnSort"] = 6;
		Db::name('mobile_btns')->insert($data);
		$data = array();
		$data["btnName"] = "领券中心";
		$data["btnSrc"] = 2;
		$data["btnUrl"] = "/pages/addons/package/pages/coupon/coupon";
		$data["btnImg"] = "/wstmart/weapp/view/weapp/addons/closure/coupon/image/coupon.png";
		$data["addonsName"] = "Coupon";
		$data["btnSort"] = 6;
		Db::name('mobile_btns')->insert($data);
	
	}
	
	public function delMobileBtn(){
		Db::name('mobile_btns')->where(["addonsName"=>"Coupon"])->delete();
	}
    /**
     * 获取优惠券信息
     */
	public function getById($id = 0){
		$id = ($id>0)?$id:(int)input('id/d');
		$shopId = (int)session('WST_USER.shopId');
		$couppn = $this->where(['couponId'=>$id,'dataFlag'=>1,'shopId'=>$shopId])->find();
		$couppn['goods'] = [];
		//判断是否需要加载商品信息
		if($couppn['useObjects']==1){
			$couppn['goods'] = Db::name('coupon_goods')->alias('cg')
			       ->join('__GOODS__ g','g.goodsId=cg.goodsId and g.isSale=1 and g.dataFlag=1 and g.goodsStatus=1','inner')
			       ->where('cg.couponId',$id)
			       ->field('goodsImg,goodsName,g.goodsId,marketPrice,shopPrice,goodsType')
			       ->select();
		}
		return $couppn;
	}
    /**
     * 获取优惠券信息
     */
	public function getByView($id){
		$id = ($id>0)?$id:(int)input('id/d');
		return $this->alias('c')->join('__SHOPS__ s','c.shopId=s.shopId')
		       ->where(['c.couponId'=>$id,'c.dataFlag'=>1])
		       ->field('c.*,s.shopName')
		       ->find();
	}
	/**
	 * 获取优惠券下边的商品列表
	 */
	public function pageQueryByCouponGoods(){
		//查询条件
		$keyword = input('keyword');
		$where = [];
		if($keyword!='')$where[] = ['goodsName','like','%'.$keyword.'%'];
		//排序条件
		$orderBy = input('condition/d',0);
		$orderBy = ($orderBy>=0 && $orderBy<=4)?$orderBy:0;
		$order = (input('desc/d',0)==1)?1:0;
		$pageBy = ['saleNum','shopPrice','visitNum','saleTime'];
		$pageOrder = ['desc','asc'];
		$couponId = (int)input('couponId/d',0);
		$coupon = $this->where(['couponId'=>$couponId,'dataFlag'=>1])->find();
		if($coupon['useObjects']==1){
	        return Db::name('coupon_goods')->alias('cg')
				     ->join('__GOODS__ g','g.goodsId=cg.goodsId and g.isSale=1 and g.dataFlag=1 and g.goodsStatus=1','inner')
				     ->where('cg.couponId',$couponId)
				     ->where($where)
				     ->field('goodsImg,goodsName,g.goodsId,marketPrice,shopPrice,goodsType,appraiseNum,saleNum')
				     ->order($pageBy[$orderBy]." ".$pageOrder[$order].",goodsId asc")
                     ->paginate(input('pagesize/d'))->toArray();
		}else{
			return Db::name('goods')
			         ->where('isSale=1 and dataFlag=1 and goodsStatus=1 and shopId='.(int)$coupon['shopId'])
			         ->where($where)
				     ->field('goodsImg,goodsName,goodsId,marketPrice,shopPrice,goodsType,appraiseNum,saleNum')
				     ->order($pageBy[$orderBy]." ".$pageOrder[$order].",goodsId asc")
                     ->paginate(input('pagesize/d'))->toArray();
		}
	}

    /**
     * 查询商品
     */
    public function searchGoods(){
    	$shopId = (int)session('WST_USER.shopId');
    	$shopCatId1 = (int)input('post.shopCatId1');
    	$shopCatId2 = (int)input('post.shopCatId2');
    	$goodsName = input('post.goodsName');
    	$where = [];
    	$where['goodsStatus'] = 1;
    	$where['dataFlag'] = 1;
    	$where['isSale'] = 1;
    	$where['shopId'] = $shopId;
    	if($shopCatId1>0)$where['shopCatId1'] = $shopCatId1;
    	if($shopCatId2>0)$where['shopCatId2'] = $shopCatId2;
    	if($goodsName!='')$where[] = ['goodsName|goodsSn','like','%'.$goodsName.'%'];
    	$rs = Db::name('goods')->where($where)->field('goodsImg,goodsName,goodsId,marketPrice,shopPrice,goodsType')->order('goodsName asc')->select();
        return WSTReturn('',1,$rs);
    }

    public function updateCouponUserUseStatus($couponsId, $userId, $data)
    {
        $status = Db::name('coupon_users')->where(['couponId'=>$couponsId, 'userId'=>$userId])->update($data);
        if (!$status) throw AE::factory(AE::DATA_UPDATE_FAIL);
        return $status;
    }

    /**
     * 新增优惠前
     */
    public function add(){
    	$data = input('post.');
    	unset($data['couponId']);
    	$shopId = (int)session('WST_USER.shopId');
    	$goodsIds = explode(',',$data['useObjectIds']);
    	$validate = new Validate;
        if(!$validate->check($data)){
        	return WSTReturn($validate->getError());
        }
        $goods = [];
        if($data['useObjects']==1){
	        $goods = Db::name('goods')->where([['goodsId','in',$goodsIds],['shopId','=',$shopId],['isSale','=',1],['goodsStatus','=',1],['dataFlag','=',1]])
	                   ->field('goodsId,goodsCatIdPath')->select();
	        if(empty($goods))return WSTReturn('请选择优惠券适用的商品');
	    }
        $data['shopId'] = $shopId;
        $data['createTime'] = date('Y-m-d H:i:s');
        Db::startTrans();
		try{
	    	$result = $this->allowField(true)->save($data);
	    	if(false !== $result){
	    		if($data['useObjects']==1){
		    		$goodsCatIds = [];
		    		//保存优惠券适用的商品
		    		$arr = [];
		            for($i=0;$i<count($goods);$i++){
			    		$cgoods = [];
			    		$cgoods['goodsId'] = $goods[$i]['goodsId'];
		                $cgoods['couponId'] = $this->couponId;
		                $arr[] = $cgoods;
		                $goodsCatId = explode('_',$goods[$i]['goodsCatIdPath']);
		                if(!in_array((int)$goodsCatId[0],$goodsCatIds))$goodsCatIds[] = (int)$goodsCatId[0];
			    	}
			    	Db::name('coupon_goods')->insertAll($arr);
		    		//保存优惠券涉及的分类
		    		$arr = [];
		    		foreach ($goodsCatIds as $key => $v) {
		    			$cgoods = [];
		    			$cgoods['catId'] = $v;
		    			$cgoods['shopId'] = $shopId;
		                $cgoods['couponId'] = $this->couponId;
		                $arr[] = $cgoods;
		    		}
		    		Db::name('coupon_cats')->insertAll($arr);
		    	}else{
		    		//获取所有分类
		    		$cats = Db::name('goods_cats')->where(['dataFlag'=>1,'parentId'=>0])->field('catId')->select();
                    $arr = [];
		    		foreach ($cats as $key => $v) {
		    			$cgoods = [];
		    			$cgoods['catId'] = $v['catId'];
		    			$cgoods['shopId'] = $shopId;
		                $cgoods['couponId'] = $this->couponId;
		                $arr[] = $cgoods;
		    		}
		    		Db::name('coupon_cats')->insertAll($arr);
		    	}
	    	}
	    	Db::commit();
	    	return WSTReturn('新增成功',1);
	    }catch (\Exception $e) {
	    	echo $e;
	 		Db::rollback();
	  		return WSTReturn('新增失败');
	   	}
    }
    /**
     * 编辑优惠券
     */
    public function edit(){
        $data = input('post.');
        $shopId = (int)session('WST_USER.shopId');
    	$goodsIds = explode(',',$data['useObjectIds']);
    	$validate = new Validate;
        if(!$validate->check($data)){
        	return WSTReturn($validate->getError());
        }
        $goods = [];
        if($data['useObjects']==1){
	        $goods = Db::name('goods')->where([['goodsId','in',$goodsIds],['shopId','=',$shopId],['isSale','=',1],['goodsStatus','=',1],['dataFlag','=',1]])
	                   ->field('goodsId,goodsCatIdPath')->select();
	        if(empty($goods))return WSTReturn('请选择优惠券适用的商品');
	    }else{
            $data['useObjectIds'] = '';
        }
        $couponNum = $this->where('couponId',$data['couponId'])->field('couponNum')->find();
        if($data['couponNum']<$couponNum['couponNum'])return WSTReturn('发行量不能比已设置发行量还小');
        WSTUnset($data,'shopId,createTime,dataFlag');
        
        Db::startTrans();
		try{
	    	$result = $this->allowField(true)->update($data,['couponId'=>$data['couponId'],'shopId'=>$shopId]);
	    	if(false !== $result){
	    		Db::name('coupon_goods')->where('couponId',$data['couponId'])->delete();
	    		Db::name('coupon_cats')->where('couponId',$data['couponId'])->delete();
	    		if($data['useObjects']==1){
	    			$goodsCatIds = [];
	    			//保存优惠券适用的商品
		    		$arr = [];
		            for($i=0;$i<count($goods);$i++){
			    		$cgoods = [];
			    		$cgoods['goodsId'] = $goods[$i]['goodsId'];
		                $cgoods['couponId'] = $data['couponId'];
		                $arr[] = $cgoods;
		                $goodsCatId = explode('_',$goods[$i]['goodsCatIdPath']);
		                if(!in_array((int)$goodsCatId[0],$goodsCatIds))$goodsCatIds[] = (int)$goodsCatId[0];
			    	}
			    	Db::name('coupon_goods')->insertAll($arr);
			    	//保存优惠券涉及的分类
		    		$arr = [];
		    		foreach ($goodsCatIds as $key => $v) {
		    			$cgoods = [];
		    			$cgoods['catId'] = $v;
		    			$cgoods['shopId'] = $shopId;
		                $cgoods['couponId'] = $data['couponId'];
		                $arr[] = $cgoods;
		    		}
		    		Db::name('coupon_cats')->insertAll($arr);
			    }else{
		    		//获取所有分类
		    		$cats = Db::name('goods_cats')->where(['dataFlag'=>1,'parentId'=>0])->field('catId')->select();
                    $arr = [];
		    		foreach ($cats as $key => $v) {
		    			$cgoods = [];
		    			$cgoods['catId'] = $v['catId'];
		    			$cgoods['shopId'] = $shopId;
		                $cgoods['couponId'] = $data['couponId'];
		                $arr[] = $cgoods;
		    		}
		    		Db::name('coupon_cats')->insertAll($arr);
		    	}
	    	}
	    	Db::commit();
	    	return WSTReturn('编辑成功',1);
	    }catch (\Exception $e) {
	 		Db::rollback();
	  		return WSTReturn('编辑失败');
	   	}
    }

    /**
     * 删除优惠券
     */
    public function del(){
    	$shopId = (int)session('WST_USER.shopId');
    	$id = (int)input('id/d',0);
    	$result = $this->where(['couponId'=>$id,'shopId'=>$shopId])->update(['dataFlag'=>-1]);
    	if(false !== $result){
    		//Db::name('coupon_users')->where('couponId',$id)->delete();
    		Db::name('coupon_goods')->where('couponId',$id)->delete();
    		Db::name('coupon_cats')->where('couponId',$id)->delete();
    	}
    	return WSTReturn('删除成功',1);
    }

    /**
     * 商家-优惠券列表
     */
    public function pageQueryByShop(){
    	$useCondition = (int)input('useCondition/d',-1);
        $shopId = (int)session('WST_USER.shopId');
        $where = ['dataFlag'=>1,'shopId'=>$shopId];
        $where[] = ['type', 'in', [0,2,4]];
        if(in_array($useCondition,[0,1]))$where['useCondition'] = $useCondition;
    	$page =  $this->where($where)
                     ->order('createTime desc')
                     ->paginate(input('pagesize/d'))->toArray();
        $page['status'] = 1;
        return $page;
    }

    public function grantQueryByPage(){
    	//$useCondition = (int)input('useCondition/d',-1);
        $shopId = (int)session('WST_USER.shopId');
        $where = ['dataFlag'=>1,'shopId'=>$shopId,'type'=>4];
        //if(in_array($useCondition,[0,1]))$where['useCondition'] = $useCondition;
    	$page =  $this->where($where)
                     ->order('createTime desc')
                     ->paginate(input('pagesize/d'))->toArray();
    	foreach ($page['data'] as $k=>$v) {
    	    $page['data'][$k]['stat'] = ($v['couponNum']-$v['receiveNum']) > 0 ? $v['couponNum']-$v['receiveNum'] : 0;
        }
        $page['status'] = 1;
        return $page;
    }

    /**
     * 获取前台优惠券列表(原版)
     */
//    public function pageCouponQuery($uId=0){
//    	$catId = (int)input('catId/d',-1);
//    	$useCondition = (int)input('useCondition/d',-1);
//        $userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
//        $where['c.dataFlag'] = 1;
//        $where[] = ['endDate','>=',date('Y-m-d')];
//        if(in_array($useCondition,[0,1]))$where['useCondition'] = $useCondition;
//        if($catId<=0){
//	    	$page =  Db::name('coupons')->alias('c')
//	    	              ->join('__SHOPS__ s','c.shopId=s.shopId and s.dataFlag=1 and s.shopStatus=1')
//	    	              ->where($where)
//	    	              ->field('shopName,shopImg,c.*')
//	                      ->order('c.endDate desc')
//                      ->paginate(input('pagesize/d'))->toArray();
//        }else{
//        	$where['cg.catId'] = $catId;
//        	$page =  Db::name('coupon_cats')->alias('cg')
//	    	              ->join('__COUPONS__ c','cg.couponId=c.couponId')
//	    	              ->join('__SHOPS__ s','c.shopId=s.shopId and s.dataFlag=1 and s.shopStatus=1')
//	    	              ->where($where)
//	    	              ->field('shopName,shopImg,c.*')
//	                      ->order('c.endDate desc')
//                      ->paginate(input('pagesize/d'))->toArray();
//        }
//        $userCoupons = [];
//        if($userId>0){
//	        $userCoupons = Db::name('coupon_users')->where(['userId'=>$userId])->column('couponId');
//	    }
//        $time = time();
//        foreach ($page['data'] as $key => $v) {
//        	$page['data'][$key]['isOut'] = (($v['couponNum']<=$v['receiveNum']) || ($time>WSTStrToTime($v['endDate']." 23:59:59")))?true:false;
//            $page['data'][$key]['isReceive'] = ($userId>0)?in_array($v['couponId'],$userCoupons):false;
//        }
//        return $page;
//    }
    /**
     * 获取前台优惠券列表
     */
    public function pageCouponQuery($userId=0, $exchange = 0, $offset=1, $pagesize=5, $minScore = false){
        //$useCondition = (int)input('useCondition/d',-1);
        $where['c.dataFlag'] = 1;
        $where[] = ['endDate','>=',date('Y-m-d')];
        $where['type'] = $exchange;
        $page =  Db::name('coupons')->alias('c')
            ->join('__SHOPS__ s','c.shopId=s.shopId and s.dataFlag=1 and s.shopStatus=1')
            ->where($where)
            ->field('shopName,shopImg,c.*')
            ->order('c.endDate desc');
        $db = clone $page;
        $db1 = clone $page;
        $data['total'] = $db->count();
        $list = $db1->limit(($offset-1)*$pagesize, $pagesize)->select();
        $userCoupons = [];
        if($userId>0){
            $userCoupons = Db::name('coupon_users')->where(['userId'=>$userId])->column('couponId');
        }
        $time = time();
        foreach ($list as $key => $v) {
            $data['list'][$key]['shopName'] = $v['shopName'];
            $data['list'][$key]['shopImg'] = $v['shopImg'];
            $data['list'][$key]['couponId'] = $v['couponId'];
            $data['list'][$key]['couponValue'] = $v['couponValue'];
            $data['list'][$key]['useMoney'] = $v['useMoney'];
            $data['list'][$key]['useCondition'] = $v['useCondition'];
            $data['list'][$key]['startDate'] = $v['startDate'];
            $data['list'][$key]['endDate'] = $v['endDate'];
            $data['list'][$key]['useObjects'] = $v['useObjects'];
            $data['list'][$key]['surplus'] = ($v['couponNum']-$v['receiveNum'] >= 0) ? $v['couponNum']-$v['receiveNum'] : 0;
            $data['list'][$key]['isOut'] = (($v['couponNum']>$v['receiveNum']) || ($time<WSTStrToTime($v['endDate']." 23:59:59") && $time>WSTStrToTime($v['startDate']." 23:59:59")))?true:false;
            $data['list'][$key]['isReceive'] = ($userId>0)?in_array($v['couponId'],$userCoupons):false;
        }
        $data['offset'] = $offset;
        $data['pagesize'] = $pagesize;
        return $data;
    }

    /**
     * 领取优惠券(原版)
     */
//    public function receive($uId=0){
//    	$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
//    	$couponId = (int)input('couponId/d',0);
//    	if($userId==0 || $couponId<=0)return WSTReturn('领取优惠券失败');
//    	$coupon = $this->get($couponId);
//    	if($coupon->dataFlag==-1)return WSTReturn('领取优惠券失败');
//    	if($coupon->couponNum<=$coupon->receiveNum)return WSTReturn('对不起，优惠券已领完');
//    	Db::startTrans();
//		try{
//			//查询用户是否领取过，是否已超过临取数量
//			$receiveNum = Db::name('coupon_users')->where(['userId'=>$userId,'couponId'=>$couponId])->count();
//			if($coupon->limitNum!=0){
//                if($receiveNum>=$coupon->limitNum)return WSTReturn('对不起，该优惠券您的领取已达上限');
//			}
//			$couponUser = [];
//            $couponUser['shopId'] = $coupon->shopId;
//            $couponUser['couponId'] = $coupon->couponId;
//            $couponUser['userId'] = $userId;
//            $couponUser['isUse'] = 0;
//            $couponUser['createTime'] = date('Y-m-d h:i:s');
//            Db::name('coupon_users')->insert($couponUser);
//            $coupon->receiveNum = $coupon->receiveNum+1;
//            $coupon->save();
//            Db::commit();
//	    	return WSTReturn('领取优惠券成功',1);
//	    }catch (\Exception $e) {
//	 		Db::rollback();
//	  		return WSTReturn('领取优惠券失败');
//	   	}
//    }

    /**
     * 领取优惠券
     */
    public function receive($userId, $couponId){
        $coupon = $this->get($couponId);
        if (!$coupon) throw AE::factory(AE::COM_PARAMS_ERR);
        if($coupon->dataFlag==-1) throw AE::factory(AE::COUPONS_GET_FAIL);
        if($coupon['type']!=0 ) throw AE::factory(AE::COUPONS_NO_USE_EXISTS);
        if(time() > strtotime($coupon->endDate.' 23:59:59')) throw AE::factory(AE::COUPONS_DATE_EXPIRE);
        if($coupon->couponNum<=$coupon->receiveNum) throw AE::factory(AE::COUPONS_NULL);
        //查询用户是否领取过，是否已超过临取数量
        $receiveNum = Db::name('coupon_users')->where(['userId'=>$userId,'couponId'=>$couponId])->count();
        if($coupon->limitNum>0){
            if($receiveNum>=$coupon->limitNum)throw AE::factory(AE::COUPONS_NUM_TOMAX);
        }
        $couponUser = [];
        Db::startTrans();
        try{
            $couponUser['shopId'] = $coupon->shopId;
            $couponUser['couponId'] = $coupon->couponId;
            $couponUser['userId'] = $userId;
            $couponUser['isUse'] = 0;
            $couponUser['createTime'] = date('Y-m-d H:i:s');
            $couponUser['endTime'] = $coupon->endDate;
            Db::name('coupon_users')->insert($couponUser);
            $coupon->receiveNum = $coupon->receiveNum+1;
            $coupon->save();
            Db::commit();
            //消息通知
            (new News())->redPacketsReceive($coupon, $userId);
            return true;
        }catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 获取商品是否有满减券
     */
    public function getGoodsCouponTags($goodsId){
    	$time = date('Y-m-d');
    	//查询是否有针对该商品的优惠券
        $hasCoupon = Db::name('coupon_goods')->alias('cg')
                    ->join('__COUPONS__ c','cg.couponId=c.couponId')
                    ->where([['endDate','>=',$time],['goodsId','=',$goodsId],['dataFlag','=',1]])->count();
        if($hasCoupon>0)return 1;
        //查询一下是否有针对分类的优惠券
        $goods = Db::name('goods')->where('goodsId',$goodsId)->field('goodsCatIdPath,shopId')->find();
        $goodsCatIdPath = explode('_',$goods['goodsCatIdPath']);
        $hasCoupon = Db::name('coupon_cats')->alias('cg')
                       ->join('__COUPONS__ c','cg.couponId=c.couponId')
                       ->where([['endDate','>=',$time],['catId','=',(int)$goodsCatIdPath[0]],['cg.shopId','=',$goods['shopId']],['dataFlag','=',1]])->count();
        if($hasCoupon>0)return 1;
        return 0;
    }

    /**
     * 加载合适的商品优惠券
     */
//    public function getCouponsByGoods($uId=0){
//    	$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
//        $goodsId = (int)input('goodsId/d',0);
//        $shopId = Db::name('goods')->where('goodsId',$goodsId)->value('shopId');
//        //获取优惠券列表
//        $rs =  Db::name('coupons')->where(['dataFlag'=>1,'shopId'=>$shopId])->order('couponValue asc')->select();
//        //获取已领优惠券列表
//        $reRs = Db::name('coupon_users')->where('userId',$userId)->column('couponId');
//        $coupons = [];
//        $time = time();
//        foreach ($rs as $key => $v) {
//        	$v['isReceive'] = false;
//        	//过期的优惠券
//        	if($time > WSTStrToTime($v['endDate']." 23:59:59"))continue;
//            //指定商品，但又不是本商品的优惠券
//            if($v['useObjects']==1){
//            	$ids = explode(',',$v['useObjectIds']);
//            	if(!in_array($goodsId,$ids))continue;
//            }
//            if(in_array($v['couponId'],$reRs))$v['isReceive'] = true;
//            unset($v['dataFlag'],$v['createTime'],$v['useObjectIds'],$v['useObjects']);
//            $coupons[] = $v;
//        }
//        return WSTReturn('',1,$coupons);
//    }

    /*
    * 根据商品获取优惠券
    */
//    public function getCouponsByGoods($userId, $goodsId, $count, $isPintuan, $tuanId=0, $specId=0)
//    {
//        if ($isPintuan==1 && $tuanId>0) {
//            $goodsPrice = model('common/Pintuans')->getPintuanData(['tuanId'=>$tuanId]);
//        } else {
//            $g = new Goods();
//            if ($specId > 0) {
//                $goodsSpec = $g->getGoodsSpec($specId);
//                $goodsPrice = $goodsSpec['specPrice']*$count;
//            } else {
//                $goodsSpec = $g->getGoodsById($goodsId);
//                $goodsPrice =$goodsSpec->shopPrice*$count;
//            }
//        }
//        $shopId = Db::name('goods')->where('goodsId',$goodsId)->value('shopId');
//        //获取优惠券列表
//        $rs =  Db::name('coupons')->where(['dataFlag'=>1,'shopId'=>$shopId])->order('couponValue asc')->select();
//        //print_r($rs);die;
//        //获取已领优惠券列表
//        $reRs = Db::name('coupon_users')->where(['userId'=>$userId, 'isUse'=>0])->column('couponId');
//        $coupons['list'] = [];
//        $time = time();
//        foreach ($rs as $key => $v) {
//        	$v['isReceive'] = false;
//        	//过期的优惠券
//        	if($time > WSTStrToTime($v['endDate']." 23:59:59"))continue;
//            //指定商品，但又不是本商品的优惠券
//            if($v['useObjects']==1){
//            	$ids = explode(',',$v['useObjectIds']);
//            	if(!in_array($goodsId,$ids))continue;
//            }
//            if ($v['useCondition']==1) {
//                if ($v['useMoney']>$goodsPrice) continue;
//            }
//            //是否已经领取
//            if(in_array($v['couponId'],$reRs)) {
//                $v['isReceive'] = true;
//            } else {
//                continue;
//            }
//            unset($v['dataFlag'],$v['createTime'],$v['useObjectIds'],$v['useObjects']);
//            $coupons['list'][] = $v;
//        }
//        $coupons['total'] =count($coupons['list']);
//        return $coupons;
//    }

    public function getCouponsByGoods($userId, $goodsId, $count, $isPintuan, $tuanId=0, $specId=0, $offset=1, $pagesize=10)
    {
        $g = new Goods();
        $o = new O();
        $goods = $g->getGoodsById($goodsId);
        $goodsPrice = $o->getGoodsPrice($goodsId, $specId, $isPintuan, $tuanId, $goods->shopPrice);
        $goodsPrice = bcmul($goodsPrice,$count,2);
        $list = Db::name('goods')->alias('g')
            ->join('coupon_users cu', 'cu.shopId=g.shopId', 'left')
            ->join('coupons c', 'c.couponId=cu.couponId', 'left')
            ->field('c.*,cu.id as couponId, cu.endTime as endDate')
            ->where('goodsId',$goodsId)
            ->where(['cu.userId'=>$userId, 'cu.isUse'=>0])
            ->order('createTime', 'desc')
            ->select();
        $da = [];
        foreach ($list as $k=>$v) {
            if ($v['useCondition']==1 && $v['useMoney']>$goodsPrice) continue;
            if($v['useObjects']==1){
            	$ids = explode(',',$v['useObjectIds']);
            	if(!in_array($goodsId,$ids)) continue;
            }
            if ($v['type'] ==0 || $v['type'] ==3) {
                if(time() < WSTStrToTime($v['startDate']." 00:00:00")) continue;
            }
            if(time() > WSTStrToTime($v['endDate']." 23:59:59")) continue;
            unset($v['dataFlag'],$v['createTime']);
            $da[$k] = $v;
        }
        $data['total'] = count($da);
        $data['list'] = array_slice($da, ($offset-1)*$pagesize, $pagesize-1);
        $data['offset'] = $offset;
        $data['pagesize'] = $pagesize;
        return $data;
    }

    /**
     * 加载店铺下的优惠券
     */
    public function getCouponsByShop($shopId = 0,$uId=0){
    	$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
    	$shopId = ($shopId>0)?$shopId:(int)input('shopId/d',0);
        //获取优惠券列表
        $rs =  Db::name('coupons')->where(['dataFlag'=>1,'shopId'=>$shopId])->order('couponValue asc')->select();
        //获取已领优惠券列表
        $reRs = Db::name('coupon_users')->where(['userId'=>$userId,'shopId'=>$shopId])->column('couponId');
        $coupons = [];
        $time = time();
        foreach ($rs as $key => $v) {
        	$v['isReceive'] = false;
        	//过期的优惠券
        	if($time > strtotime($v['endDate']." 23:59:59"))continue;
            if(in_array($v['couponId'],$reRs))$v['isReceive'] = true;
            unset($v['dataFlag'],$v['createTime'],$v['useObjectIds'],$v['useObjects']);
            $coupons[] = $v;
        }
        return WSTReturn('',1,['coupons'=>$coupons,'receive'=>count($reRs)]);
    }

    /**
     * 加载已领未使用的商品优惠券
     * 1.【指定商品】要商品符合
     * 2.【指定商品】要商品总价符合
     * 3.【店铺通用】订单总价符合
     */
    public function getAvailableCoupons($cartGoods,$shopId,$uId=0){
    	//构造用于比较的数组
    	$carts = ['ids'=>[],'totalMoney'=>0];//存放优惠券里指定的商品id，每个商品的总价,订单总价
    	foreach($cartGoods as $key =>$v){
            $carts['ids'][] = $v['goodsId'];
            $carts[$v['goodsId']]['totalMoney'] = $v['cartNum']*$v['shopPrice'];
            $carts['totalMoney'] += $v['cartNum']*$v['shopPrice'];
    	}
    	$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
        //获取该店优惠券列表
        $rs =  Db::name('coupons')->where(['dataFlag'=>1,'shopId'=>$shopId])->order('couponValue asc')->select();
        //获取该店已领未用的优惠券列表
        $reRs = Db::name('coupon_users')->where(['userId'=>$userId,'isUse'=>0,'shopId'=>$shopId])->column('couponId');
        $coupons = [];
        $time = time();
        foreach ($rs as $key => $v) {
        	//过期的优惠券要过滤
        	if($time > strtotime($v['endDate']." 23:59:59"))continue;
            //如果没有领取的优惠券也过滤掉
            if(!in_array($v['couponId'],$reRs))continue;
            //指定商品，但又不是本商品的优惠券要过滤
            if($v['useObjects']==1){
            	$ids = explode(',',$v['useObjectIds']);
            	//判断两个数组是否有交集，没有交集则跳过
            	$intersection = array_intersect($carts['ids'],$ids);
            	if(empty($intersection))continue;
            	//取数组内的交易进行判断金额是否满足，有一个满足的话都通过
            	$isFind = false;
            	foreach ($intersection as $gkey => $goodsId) {
            		//有设置使用条件
                    if($v['useCondition']==1){
                    	if($v['useMoney']<=$carts[$goodsId]['totalMoney']){
	                        $isFind = true;
	                        continue;
	                    }
	            	}else{
	            		$isFind = true;
	                    continue;
	            	}
            	}
            	if(!$isFind)continue;
            }else{
                //商品总价不符合的要过滤
            	if($v['useCondition']==1 && $v['useMoney']>$carts['totalMoney'])continue;
            }
            unset($v['dataFlag'],$v['createTime'],$v['useObjectIds'],$v['useObjects']);
            $coupons[] = $v;
        }
        return $coupons;
    }

    /**
     * 计算订单金额
     */
    public function calculateCartMoney($params){
    	$couponIds = input('couponIds');
        if($couponIds=='')return;
        $couponIds = explode(',',$couponIds);
        $shopCoupons = [];
        foreach ($couponIds as $key => $v) {
            $tmp = explode(':',$v);
            if((int)$tmp[0]<=0 || (int)$tmp[1]<=0)continue;
            $shopCoupons[$tmp[0]] = (int)$tmp[1];
        }
        if(empty($shopCoupons))return;
        $derateMoney = 0;
        //根据优惠券组建店铺优惠券对应数组
        foreach ($params['carts']['carts'] as $key => $v) {
            $coupons = $this->getAvailableCoupons($v['list'],$v['shopId'],$params['uId']);
            //校验优惠券是否有效
            $rightCoupon = [];
            foreach ($coupons as $ckey => $cv) {
                if(isset($shopCoupons[$cv['shopId']]) && $cv['couponId']==$shopCoupons[$cv['shopId']])$rightCoupon = $cv;
            }

            if(empty($rightCoupon))continue;
            //计算出店铺可以优惠后的价格
            if($rightCoupon['useCondition']==1){
                if($params['data']['shops'][$key]['oldGoodsMoney']>=$rightCoupon['useMoney']){
                    $derateMoney = $derateMoney + $rightCoupon['couponValue'];
                }
            }else{
                $derateMoney = $derateMoney + $rightCoupon['couponValue'];
            }
            $params['data']['shops'][$key]['goodsMoney'] = WSTPositiveNum($params['data']['shops'][$key]['goodsMoney'] - $derateMoney);
        }
        $params['data']['totalMoney'] = WSTPositiveNum($params['data']['totalMoney'] - $derateMoney);
    }

    /**
     * 计算虚拟商品购物车金额
     */
    public function calculateVirtualCartMoney($params){
    	$couponIds = input('couponIds');
        if($couponIds=='')return;
        $couponIds = explode(',',$couponIds);
        $shopCoupons = [];
        foreach ($couponIds as $key => $v) {
            $tmp = explode(':',$v);
            if((int)$tmp[0]<=0 || (int)$tmp[1]<=0)continue;
            $shopCoupons[$tmp[0]] = (int)$tmp[1];
        }
        if(empty($shopCoupons))return;
        $derateMoney = 0;
        //根据优惠券组建店铺优惠券对应数组
        foreach ($params['carts']['carts'] as $key => $v) {
            $coupons = $this->getAvailableCoupons($v['list'],$v['shopId']);
            //校验优惠券是否有效
            $rightCoupon = [];
            foreach ($coupons as $ckey => $cv) {
                if($cv['couponId']==$shopCoupons[$cv['shopId']])$rightCoupon = $cv;
            }

            if(empty($rightCoupon))continue;
            //计算出店铺可以优惠后的价格
            if($rightCoupon['useCondition']==1){
                if($params['data']['shops'][$key]['goodsMoney']>=$rightCoupon['useMoney']){
                    $derateMoney = $rightCoupon['couponValue'];
                }
            }else{
                $derateMoney = $rightCoupon['couponValue'];
            }
            $params['data']['shops'][$key]['goodsMoney'] = WSTPositiveNum($params['data']['shops'][$key]['goodsMoney'] - $derateMoney);
        }
        $params['data']['totalMoney'] = WSTPositiveNum($params['data']['totalMoney']-$derateMoney);
    }

    /**
     * 订单执行前插入
     */
    public function beforeInsertOrder($params){
        $carts = $params['carts'];
        $order = $params['order'];
        $couponId = (int)input('couponId_'.$order['shopId']);
        $shopCart = $carts['carts'][$order['shopId']];
        $coupon = [];
        foreach ($shopCart['coupons'] as $key => $v) {
            if($couponId==$v['couponId']){
                $coupon = $v;
                break;
            }
        }
        if(!empty($coupon)){
            //加载未使用的优惠券
            $couponUser = Db::name('coupon_users')->where(['userId'=>$order['userId'],'isUse'=>0,'couponId'=>$coupon['couponId']])->limit(1)->select();
            if(!empty($couponUser)){
            	$couponUser = $couponUser[0];
            	Db::name('coupon_users')->where(['id'=>$couponUser['id']])->update(['isUse'=>1,'orderNo'=>$order['orderNo'],'useTime'=>date('Y-m-d H:i:s')]);
                //使用优惠券
                $params['order']['userCouponId'] = $couponUser['id'];
                if($coupon['useCondition']==1){
                    $params['order'] ['userCouponJson'] = json_encode(['text'=>'满'.$coupon['useMoney']."减".$coupon['couponValue'],'money'=>$coupon['couponValue']]);
                }else{
	                $params['order'] ['userCouponJson'] = json_encode(['text'=>"优惠券￥".$coupon['couponValue'],'money'=>$coupon['couponValue']]);
	            }
                //修改订单信息
                $realTotalMoney = $order['realTotalMoney']-$coupon['couponValue'];
                $params['order']['realTotalMoney'] = ($realTotalMoney>0)?$realTotalMoney:0;
                $params['order']['needPay'] = $params['order']['realTotalMoney'];
                if($params['order']['needPay']<=0){
					$params['order']['orderStatus'] = 0;//待发货
					$params['order']['isPay'] = 1;
				}
            }
        }
    }

    /**
     * 用户-优惠券列表
     */
    public function pageQueryByUser($userId=0, $status, $offset=1, $pagesize=5){
        $where = ['cu.userId'=>$userId];
        $where2 = '';
        if($status==0)$where2 =' isUse=0 and cu.endTime>="'.date('Y-m-d').'"';
        if($status==1)$where2 =' isUse=1 ';
        if($status==2)$where2 =' isUse=0 and cu.endTime<"'.date('Y-m-d').'"';
    	$page =  $this->alias('c')->join('__COUPON_USERS__ cu','c.couponId=cu.couponId')
    	              ->join('__SHOPS__ s','c.shopId=s.shopId and s.dataFlag=1 and s.shopStatus=1')
	    	          ->where($where)
	    	          ->where($where2)
	    	          ->field('c.couponId,c.couponValue,c.useCondition,c.useMoney,c.startDate,cu.endTime as endDate,c.useObjects,c.useObjectIds,s.shopName')
                      ->order('c.createTime desc');
    	$db1 = clone $page;
        $db = clone $page;
        $data['total']=$db->count();
        $data['list']=$db1 ->limit(($offset-1)*$pagesize, $pagesize)
                    ->select();
    	if ($status==0) {
    	    foreach ( $data['list'] as $k=>$v) {
    	        $end = strtotime($v['endDate'].' 23:59:59');
    	        $time = time();
    	        if (bcsub($end,$time) < 3*86400) {
                    $data['list'][$k]['quicklyExpire'] = 1;
                } else {
                    $data['list'][$k]['quicklyExpire'] = 0;
                }
            }
        }
        $data['offset'] = $offset;
        $data['pageSize'] = $pagesize;
        return $data;
    }

    /**
     * 获取用户
     */
    public function getCouponNumByUser($uId=0){
       $data = [];
       $userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
       $where = ['c.dataFlag'=>1,'cu.userId'=>$userId];
       $data['num0'] = $this->alias('c')->join('__COUPON_USERS__ cu','c.couponId=cu.couponId')
    	              ->join('__SHOPS__ s','c.shopId=s.shopId and s.dataFlag=1 and s.shopStatus=1')
	    	          ->where($where)
	    	          ->where(' isUse=0 and c.endDate>"'.date('Y-m-d').'"')
	    	          ->count();
	   $data['num1'] = $this->alias('c')->join('__COUPON_USERS__ cu','c.couponId=cu.couponId')
    	              ->join('__SHOPS__ s','c.shopId=s.shopId and s.dataFlag=1 and s.shopStatus=1')
	    	          ->where($where)
	    	          ->where(' isUse=1 ')
	    	          ->count();
	   $data['num2'] = $this->alias('c')->join('__COUPON_USERS__ cu','c.couponId=cu.couponId')
    	              ->join('__SHOPS__ s','c.shopId=s.shopId and s.dataFlag=1 and s.shopStatus=1')
	    	          ->where($where)
	    	          ->where(' isUse=0 and c.endDate<"'.date('Y-m-d').'" ')
	    	          ->count();
	   return $data;
    }

    /**
     * 更改店铺状态时的处理函数
     */
    public function afterChangeShopStatus($params){
        $shopId = (int)$params['shopId'];
        if($shopId<=0)return;
        $shop = model('common/shops')->get($shopId);
        //店铺状态不正常的话就删除了优惠券
        if($shop->applyStatus==2 && ($shop->dataFlag!=1 || $shop->shopStatus!=1)){
             $this->where('shopId',$shopId)->update(['dataFlag'=>-1]);
        }
    }

    /**
     * 领取的优惠券数
     */
    public function couponsNum($uId=0){
    	$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
    	$rs = Db::name('coupon_users')->alias('cu')->join('__COUPONS__ c','c.couponId=cu.couponId')
    	->where('cu.userId='.$userId.' and cu.isUse=0 and c.endDate>='.date('Y-m-d'))->count();
    	return $rs;
    }

    /*
     *积分兑换优惠卷列表
     * @userId 用户id
     */
    public function exchangeCouponslist($userId, $offset=1, $pageSize=5)
    {
        $where['c.dataFlag'] = 1;
        $where['c.type'] = 3;
        $where[] = ['c.endDate', '>=', date('Y-m-d')];
        $list = $this->alias('c')
            ->join('shops s', 's.shopId=c.shopId and s.shopStatus=1 and s.dataFlag=1', 'left')
            ->where($where)
            ->field('c.couponId, c.name, c.couponValue, c.useCondition, c.useMoney, c.endDate, c.useObjects, c.integral, c.receiveNum, c.couponNum,c.isDailyLimit,c.dailyLimitNum')
            ->field('s.shopName')
            ->order('c.createTime desc');
        $db = clone $list;
        $db1 = clone $list;
        $data['total'] = $db1->count();
        $data['list'] = $db->page($offset, $pageSize)->select()->toArray();
        $redis = new Redis();
        foreach ($data['list'] as $k=>$v) {
            if ($v['isDailyLimit']==1) {
                $key = config('enum.dailyLimitNum').'_'.$v['couponId'].'_'.date('Y-m-d');
                //每日你限领数量是否超标
                $dailyLimitNum =$redis->get($key);
                $limitNum = $dailyLimitNum ? $dailyLimitNum : 0;
                $data['list'][$k]['surplus'] = $v['dailyLimitNum']-$limitNum > 0 ? $v['dailyLimitNum']-$limitNum : 0;
                $data['list'][$k]['alreadySnatch'] = $v['dailyLimitNum']-$limitNum > 0 ? true : false;
            }else {
                $data['list'][$k]['surplus'] = -1;
                $data['list'][$k]['alreadySnatch'] = true;
            }
            unset($data['list'][$k]['couponNum'],$data['list'][$k]['receiveNum'],$data['list'][$k]['dailyLimitNum'],$data['list'][$k]['isDailyLimit']);
        }
        $userScore = (new Users())->getFieldsById($userId, 'userScore');
        $data['myScore'] = $userScore['userScore'];
        $data['offset'] = $offset;
        $data['pagesize'] = $pageSize;
        return $data;
    }

    public function getCouponUserCountByCouponId($userId, $couponId)
    {
        $userCoupon = Db::name('coupon_users')->where(['userId'=>$userId, 'couponId'=>$couponId, 'isUse'=>0])->count();
        return $userCoupon;
    }

    /*
     *积分兑换优惠卷
     * @userId 用户id
     */
    public function exchangeCoupons($userId, $couponId)
    {
        //先比较下用户积分是否可以兑换
        $coupon = $this->get($couponId);
        if ($coupon['type']!=3) throw AE::factory(AE::COUPONS_NO_EXCHANGE);
        if($coupon->dataFlag==-1)throw AE::factory(AE::COUPONS_EXCHANGE_FAIL);
        //if($coupon->couponNum<=$coupon->receiveNum)throw AE::factory(AE::COUPONS_NULL);
        $userInfo = (new Users())->getUserInfo(['userId'=>$userId]);
        if ($userInfo->userScore < $coupon->integral) throw AE::factory(AE::COUPONS_NOT_EXCHANGE);
        //查询用户是否领取过，是否已超过临取数量
        //$receiveNum = Db::name('coupon_users')->where(['userId'=>$userId,'couponId'=>$couponId])->count();
        $redis = new Redis();
        $key = config('enum.dailyLimitNum').'_'.$couponId.'_'.date('Y-m-d');
        if ($coupon->isDailyLimit==1) {
            //每日你限领数量是否超标
            $dailyLimitNum =$redis->get($key);
            if ($dailyLimitNum) {
                if ($coupon->dailyLimitNum<=$dailyLimitNum) throw AE::factory(AE::COUPONS_NOT_DAILYLIMITNUM);
            }else{
                $redis->set($key, 0, (strtotime(date('Y-m-d 23:59:59'))-time()));
            }
        }
        //if($receiveNum>=$coupon->limitNum)throw AE::factory(AE::COUPONS_NUM_TOMAX);
        Db::startTrans();
        try{
            $couponUser = [];
            $couponUser['shopId'] = $coupon->shopId;
            $couponUser['couponId'] = $coupon->couponId;
            $couponUser['userId'] = $userId;
            $couponUser['isUse'] = 0;
            $couponUser['createTime'] = date('Y-m-d H:i:s');
            $couponUser['endTime'] = $coupon->endDate;
            Db::name('coupon_users')->insert($couponUser);
            $coupon->receiveNum = $coupon->receiveNum+1;
            $coupon->save();
            //用户积分要剪掉兑换的
            Db::name('users')->where('userId', $userId)->setDec('userScore', $coupon->integral);
            Db::name('users')->where('userId', $userId)->setInc('userTotalScore', $coupon->integral);
            $scoreUser = [
                'userId' => $userId,
                'score' => $coupon->integral,
                'dataType' => 'coupon',
                'dataId' => $couponId,
                'dataRemarks'=> '兑换积分',
                'scoreType' => 2,
                'createTime' => date('Y-m-d H:i:s')
            ];
            //添加积分明细
            Db::name('user_scores')->insert($scoreUser);
            $redis->inc($key,1);
            Db::commit();
            return WSTReturn('success', 1, true);
        }catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function exchangeRecord($userId)
    {
        $where['userId'] = $userId;
        $where['dataType'] = 'coupon';
    }

    public function grantCouponByPhone($couponId, $grantCouponPhone)
    {
        $coupons = $this->where('couponId', $couponId)->find();
        if (!$coupons) return WSTReturn('参数错误',-1);
        $userPhone = explode(',', $grantCouponPhone);
        $user = new Users();
        $where[]= ['userPhone', 'in', $userPhone];
        $userIds = $user->getUserColumn($where, 'userId');
        if (empty($userIds)) return WSTReturn('请填写正确格式',-1);
        $num = $coupons->couponNum-$coupons->receiveNum;
        $count = count($userIds);
        if ($num < $count) return WSTReturn('最多发送'.$num,-1);
        $couponUserarrayId = Db::name('coupon_users')->where('couponId', $couponId)->column('userId');
        Db::startTrans();
        try{
            $data = [];
            foreach ($userIds as $k=>$v) {
                if (in_array($v, $couponUserarrayId)) {
                    continue;
                }
                $data[$k]['userId'] = $v;
                $data[$k]['shopId'] = $coupons->shopId;
                $data[$k]['couponId'] = $couponId;
                $data[$k]['isUse'] = 0;
                $data[$k]['createTime'] = date('Y-m-d H:i:s');
                $data[$k]['endTime'] = $coupons->endDate;
            }
            $msg['num']='';
            if (!empty($data)) {
                Db::name('coupon_users')->data($data)->limit(10)->insertAll();
                $this->where('couponId', $couponId)->setInc('receiveNum', count($data));
                $msg['num'] = $coupons->couponNum-($coupons->receiveNum+count($data));
            }
            Db::commit();
            return WSTReturn('发送成功',1,$msg);
        }catch(\Exception $e){
            Db::rollback();
            return WSTReturn('发送失败',-1);
        }
    }
}
