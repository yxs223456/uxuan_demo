<?php
namespace wstmart\app\controller;

use wstmart\app\model\Orders as M;
use wstmart\common\model\Payments;
use wstmart\app\service\Users as ASUsers;
use wstmart\common\service\Pintuan as SPintuan;
use wstmart\app\service\Orders as O;
use wstmart\common\service\Orders as CO;
use wstmart\app\service\Users as ASUser;
use wstmart\common\exception\AppException as AE;

/**
 * 订单控制器
 */
class Orders extends Base{
    protected $beforeActionList = [
        'checkAuth'
    ];
	// 前置方法执行列表
    protected $openAction = [
        'checkAuth',
        'orderstatusnum',
        'getlist',
        'getreason',
        'pintuaninfo',
        'sharepintuan',
        'cancel',
        'delete',
        'info',
        'getaftersalelist',
        'logisticsinfo',
        'noticedeliver',
        'orderprice',
        'goodsorder',
        'getfreight',
        'quicksubmitorder',
        'orderpaystatus',
        'placeordertrue',
        'confirmcollectgoods',
        'paymoneybyzerostatus',
    ];
	/*********************************************** 用户操作订单 ************************************************************/
	/*
     * 计算总计
     */
    public function orderPrice()
    {
        $userId = ASUser::getUserByCache()['userId'];
        $goodsId = getInput('post.goodsId');
        $specId = getInput('post.specId/d', 0);
        $count = getInput('post.count/d');
        $isPintuan = getInput('post.isPintuan/d', 0);
        $couponsId = getInput('post.couponsId/d', 0);
        $addressId = getInput('post.addressId/d',0);
        $tuanId = getInput('post.tuanId');
        if (checkInt($count, false) === false || checkInt($isPintuan) === false || checkInt($specId) === false ||
            checkInt($couponsId) === false || checkInt($addressId) === false
            || checkInt($goodsId, false)==false) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $m = new O();
        $rs = $m->orderPrice($userId, $goodsId, $count, $isPintuan, $couponsId, $addressId, $specId, $tuanId);
        return $this->shopJson($rs);
    }

    /*
     * 提交订单的总接口
     */
    public function placeOrderTrue()
    {
        $goodsId = getInput('post.goodsId');
        $isPintuan = getInput('post.isPintuan/d', 0);
        $goodsSpecId =  getInput('post.specId/d');
        $count =  getInput('post.count');
        $tuanId =  getInput('post.tuanId', 0);
        $userId = ASUser::getUserByCache()['userId'];
        if (checkInt($goodsId, false)==false || checkInt($isPintuan)==false || checkInt($count, false)==false
            || checkInt($goodsSpecId)==false ) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $m = new O();
        $rs = $m->placeOrderTrue($userId, $goodsId, $isPintuan, $goodsSpecId, $tuanId, $count);
        return $this->shopJson($rs);
    }

    /*
	 * 获取订单商品详情
	 */
    public function goodsOrder()
    {
        $userId = ASUser::getUserByCache()['userId'];
        $goodsId = (int)input('goodsId/d',0);
        $specId = input('specId');
        $m = new O();
        $rs = $m->goodsOrder($userId, $goodsId, $specId);
        return $this->shopJson($rs);
    }

	/**
     * 各状态订单数量
     */
	public function orderStatusNum()
    {
        $userId = ASUser::getUserByCache()['userId'];
        $orderService = new O();
        $rs = $orderService->orderStatusNum($userId);
        return $this->shopJson($rs);
    }

    /**
     * 订单支付状态
     */
    public function orderPayStatus()
    {
        $orderNo = getInput('orderNo');
        if (empty($orderNo)) throw AE::factory(AE::COM_PARAMS_ERR);
        //获取通知的数据
        $m = new O();
        $weChatPay = $m->orderPayStatus($orderNo);
        return json($weChatPay);
    }

    /**
     * 如果支付金额为0的时候走这个接口
     */
    public function payMoneyByZeroStatus()
    {
        $orderNo = getInput('orderNo');
        if (empty($orderNo)) throw AE::factory(AE::COM_PARAMS_ERR);
        //获取通知的数据
        $m = new O();
        $weChatPay = $m->payMoneyByZeroStatus($orderNo);
        return $this->shopJson($weChatPay);
    }

    /**
     * 获取取消、拒收、退款订单操作的理由
     * @params $type 1:取消 2:拒收 4:退款
     */
    public function getReason(){
        $codeArr = ['1'=>'ORDER_CANCEL','2'=>'ORDER_REJECT','4'=>'REFUND_TYPE'];
        $type = getInput('post.type');
        $type = (isset($codeArr[$type]))?$codeArr[$type]:$type;
        $data = WSTDatas($type);
        if (is_array($data)) {
            $data = array_values($data);
        } else {
            $data = [];
        }
        return $this->shopJson($data);
    }

    /**
     * 取消订单
     */
    public function cancel()
    {
        $orderId = getInput('post.orderId');
        $cancelReason = getInput('post.cancelReason');
        if (!checkInt($orderId, false) || !checkInt($cancelReason, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }

        $orderService = new CO();
        $rs = $orderService->cancel($orderId, $cancelReason);
        return $this->shopJson($rs);
    }

    /**
     * 提醒发货
     */
    public function noticeDeliver()
    {
        $orderId = getInput('post.orderId');
        if (!checkInt($orderId, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }

        $orderService = new CO();
        $rs = $orderService->noticeDeliver($orderId);
        return $this->shopJson($rs);
    }

    /**
     * 确认收货
     */
    public function confirmCollectGoods()
    {
        $orderId = getInput('post.orderId');
        if (!checkInt($orderId, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $orderService = new CO();
        $rs = $orderService->confirmCollectGoods($orderId);
        return $this->shopJson($rs);
    }

    /**
     *删除订单
     */
    public function delete()
    {
        $orderId = getInput('post.orderId');
        if (!checkInt($orderId, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }

        $orderService = new CO();
        $rs = $orderService->delete($orderId);
        return $this->shopJson($rs);
    }

    /**
     * 订单列表
     */
    public function getList()
    {
        $offset = getInput('post.offset');
        $pageSize = getInput('post.pageSize');
        $orderStatus = getInput('post.orderStatus');
        if (!checkInt($offset, false) || !checkInt($pageSize, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $orderStatusConfig = config('web.order_status');
        if (!array_key_exists($orderStatus, $orderStatusConfig) && $orderStatus !== 'all') {
            throw AE::factory(AE::ORDER_STATUS_NOT_EXISTS);
        } else {
            $orderStatus = $orderStatus !== 'all' ? $orderStatusConfig[$orderStatus] : null;
        }
        $userId = ASUser::getUserByCache()['userId'];
        $orderService = new O();
        $rs = $orderService->getList($userId, $orderStatus, $offset, $pageSize);
        return $this->shopJson($rs);
    }

    /**
     *订单详情
     */
    public function info()
    {
        $orderId = getInput('post.orderId');
        if (!checkInt($orderId, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }

        $userId = ASUser::getUserByCache()['userId'];
        $orderService = new CO();
        $rs = $orderService->info($userId, $orderId);
        return $this->shopJson($rs);
    }

    public function logisticsInfo()
    {
        $orderId = getInput('post.orderId');
        if (!checkInt($orderId, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }

        $userId = ASUser::getUserByCache()['userId'];
        $orderService = new CO();
        $rs = $orderService->logisticsInfo($userId, $orderId);
        return $this->shopJson($rs);
    }

    /**
     * 拼团进度
     */
    public function pintuanInfo()
    {
        $tuanNo = getInput('post.tuanNo');
        if (empty($tuanNo)) {
            throw AE::factory(AE::COM_PARAMS_EMPTY);
        }

        $pintuanService = new SPintuan();
        $rs = $pintuanService->pintuanInfo($tuanNo);
        return $this->shopJson($rs);
    }

    public function sharePintuan()
    {
        $orderNo = getInput('post.orderNo');
        if (empty($orderNo)) {
            throw AE::factory(AE::COM_PARAMS_EMPTY);
        }

        $userId = ASUsers::getUserByCache()['userId'];
        $pintuanService = new SPintuan();
        $rs = $pintuanService->sharePintuan($orderNo, $userId);
        return $this->shopJson($rs);
    }

    public function getAfterSaleList()
    {
        $offset = getInput('post.offset');
        $pageSize = getInput('post.pageSize');
        $refundStatus = getInput('post.afterSaleStatus');
        if (!checkInt($offset, false) || !checkInt($pageSize, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $refundStatusConfig = config('web.refund_status');
        if (!array_key_exists($refundStatus, $refundStatusConfig) && $refundStatus !== 'all') {
            throw AE::factory(AE::ORDER_STATUS_NOT_EXISTS);
        } else {
            $refundStatus = $refundStatus !== 'all' ? $refundStatusConfig[$refundStatus] : null;
        }

        $userId = ASUser::getUserByCache()['userId'];
        $orderService = new O();
        $rs = $orderService->getAfterSaleList($userId, $refundStatus, $offset, $pageSize);
        return $this->shopJson($rs);
    }

	/**
	*  提醒发货
	*/
	/*public function noticeDeliver(){
		$m = new M();
		$userId = $m->getUserId();
        return json_encode($m->noticeDeliver($userId));
	}*/
	/**
	* 根据订单号获取物流信息
	*/
	public function getLogistics(){
		$model = new \addons\kuaidi\model\Kuaidi();
		$orderId = (int)input('orderId');
		// 订单信息
		$data['orderInfo'] = $model->getOrderInfo();
		// 快递信息
		$data['logisticInfo'] = json_decode($model->getOrderExpress($orderId),true);
		
		if(!empty($data['logisticInfo'])){
			$state = isset($data['logisticInfo']['state'])?$data['logisticInfo']['state']:-1;
			// 存在物流信息
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
			// 物流状态
			$data['orderInfo']['stateTxt'] = $stateTxt;
			$data['logisticInfo'] = isset($data['logisticInfo']['data'])?$data['logisticInfo']['data']:[];
		}
		// 域名-用于显示图片
		$data['domain'] = $this->domain();
		return json_encode(WSTReturn('ok',1,$data));
	}
    
	/**
	 * 提交订单
	 */
	public function submit(){
		$m = new M();
		$userId = $m->getUserId();
		$orderSrcArr = ['android'=>3,'ios'=>4];
		if(!isset($orderSrcArr[input('orderSrc')]))return json_encode(WSTReturn('非法订单来源',-1));
		$orderSrc = $orderSrcArr[input('orderSrc')];
		$rs = $m->submit((int)$orderSrc, $userId);
		return json_encode($rs);
	}
	/**
	 * 提交虚拟订单(原版)
	 */
	public function quickSubmit(){
		$m = new M();
		$userId = $m->getUserId();
		$rs = $m->quickSubmit(2, $userId);
		return json_encode($rs);
	}

    /**
     * 生成订单
     */
    public function quickSubmitOrder(){
        $userId = ASUser::getUserByCache()['userId'];
        $goodsId = getInput('goodsId');
        $couponsId = getInput('couponsId/d', 0);
        $goodsNum = getInput('count/d', 1);
        $specIds = getInput('specId/d', 0);
        $addressId = getInput('addressId');
        $isPintuan = getInput('isPintuan/d', 0);
        $tuanId = getInput('tuanId/d', 0);
        $orderSrc = getInput('orderSrc');
        $tuanNo = getInput('tuanNo', NULL);
        $pid = getInput('pid', '');
        if (checkInt($goodsId, false)==false || checkInt($addressId, false)==false) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        if (checkInt($isPintuan)==false || empty($orderSrc)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $rs = (new CO())->quickSubmitOrder($userId, $goodsId, $couponsId, $goodsNum, $specIds, $addressId, $isPintuan, $orderSrc, $tuanId, $tuanNo, $pid);
        return $this->shopJson($rs);
    }


	/**
	* 订单列表
	*/
	public function getOrderList(){
		/* 
		 	-3:拒收、退款列表
			-2:待付款列表 
			-1:已取消订单
			0,1: 待收货
			2:待评价/已完成
		*/
		$flag = -1;
		$type = input('param.type');
		$status = [];
		// 是否取出取消、拒收、退款订单理由
		$cancelData = $rejectData = $refundData = false;
		switch ($type) {
			case 'waitPay':
				$status=[-2];
				$cancelData = true;
				break;
			case 'waitDelivery':
				$status=[0];
				$rejectData = true;
				$cancelData = true;
				break;
			case 'waitReceive':
				$status=[1];
				$rejectData = true;
				$cancelData = true;
				break;
			case 'waitAppraise':
				$status=[2];
				$flag=0;
				break;
			case 'finish': 
				$status=[2];
				break;
			case 'abnormal': // 退款/拒收 与取消合并
				$status=[-1,-3];
				$refundData = true;
				break;
			default:
				$status=[-3,-2,-1,0,1,2];
				$cancelData = $rejectData = $refundData = true;
				break;
		}
		$m = new M();
		$userId = $m->getUserId();
		$rs = $m->userOrdersByPage($status, $flag, $userId);
		foreach($rs['data'] as $k=>$v){
			// 删除无用字段
			WSTUnset($rs['data'][$k],'shopQQ,shopWangWang,goodsMoney,totalMoney,deliverMoney,orderSrc,createTime,complainId,refundId,payTypeName,hook,isRefund');
			$a = WSTLangDeliverType(1);
			$b = WSTLangDeliverType(0);
			$rs['data'][$k]['deliverType'] = ($v['deliverType']==$a)?1:0;
			// 判断是否退款
			if(in_array($v['orderStatus'],[-1,-3]) && ($v['payType']==1) && ($v['isPay']==1) ){
				$rs['data'][$k]['status'] .= ($v['isRefund']==1)?'(已退款)':'(未退款)';
			}
			if(!empty($v['list'])){
				foreach($v['list'] as $k1=>$v1){
					$rs['data'][$k]['list'][$k1]['goodsImg'] = $v1['goodsImg'];
				}
			}
		}
		// 获取域名,用于显示图片
		$rs['domain'] = $this->domain();

		// 根据获取的type来取
		// 取消理由
		if($cancelData)$rs['cancelReason'] = WSTDatas('ORDER_CANCEL');
		// 拒收理由
		if($rejectData)$rs['rejectReason'] = WSTDatas('ORDER_REJECT');
		// 退款理由
		if($refundData)$rs['refundReason'] = WSTDatas('REFUND_TYPE');


		if(empty($rs['data']))return json_encode(WSTReturn('没有相关订单',-1));
		return json_encode(WSTReturn('请求成功', 1, $rs));
	}

	/**
	 * 订单详情
	 */
	public function getDetail(){
		$m = new M();
		$userId = (int)$m->getUserId();
		$isShop = (int)input('isShop');
		if($isShop==1){
			// 根据用户id查询店铺id
			$userId = (int)model('shops')->getShopId($userId);
		}
		$rs = $m->getByView((int)input('id'), $userId);
		if(isset($rs['status']))return json_encode($rs);
		// 删除无用字段
		unset($rs['log']);
		// 发票税号
		$invoiceArr = json_decode($rs['invoiceJson'],true);
		if(isset($invoiceArr['invoiceCode']))$rs['invoiceCode'] = $invoiceArr['invoiceCode'];
		$rs['status'] = WSTLangOrderStatus($rs['orderStatus']);
		$rs['payInfo'] = WSTLangPayType($rs['payType']);
		$rs['deliverInfo'] = WSTLangDeliverType($rs['deliverType']);
		foreach($rs['goods'] as $k=>$v){
			$v['goodsImg'] = WSTImg($v['goodsImg'],3);
		}
		// 若为取消或拒收则取出相应理由
		if($rs['orderStatus']==-1){
			if($rs['cancelReason']==0){
				$rs['cancelDesc'] = "订单长时间未支付，系统自动取消订单";
			}else{
				// 取消理由
				$reason = WSTDatas('ORDER_CANCEL');
				$rs['cancelDesc'] = $reason[$rs['cancelReason']]['dataName'];
			}
		}else if($rs['orderStatus']==-3){
			// 拒收理由
			$reason = WSTDatas('ORDER_REJECT');
			$rs['cancelDesc'] = $reason[$rs['rejectReason']]['dataName'];
		}
		// 退款理由   $rs['refundReason'] = WSTDatas('REFUND_TYPE');
		$rs['domain'] = $this->domain();
		/*******  满就送减免金额 *******/
        foreach($rs['goods'] as $k=>$v){
            if(isset($v['promotionJson']) && $v['promotionJson']!=''){// 有使用优惠券
                $rs['goods'][$k]['promotionJson'] = json_decode($v['promotionJson'],true);
                $rs['goods'][$k]['promotionJson']['extraJson'] = json_decode($rs['goods'][$k]['promotionJson']['extraJson'],true);
                // 满就送减免金额
                $rs['rewardMoney'] = $money = $rs['goods'][$k]['promotionJson']['promotionMoney'];
                break;
            }
        }
        /*********  优惠券  *********/
        if(isset($rs['userCouponId']) && $rs['userCouponId']>0){
            // 获取优惠券信息
            $money = json_decode($rs['userCouponJson'],true)['money']; // 优惠券优惠金额
            $rs['couponMoney'] = number_format($money,2);
        }
		return json_encode(WSTReturn('请求成功',1,$rs));
	}

	/**
	 * 用户确认收货
	 */
	public function receive(){
		$m = new M();
		$orderId = input('param.orderId');
		$userId = $m->getUserId();
		$rs = $m->receive($orderId, $userId);
		return json_encode($rs);
	}

	/**
	* 用户-评价页
	*/
	public function orderAppraise(){
		$m = model('Orders');
		$oId = (int)input('oId');
		//根据订单id获取 商品信息
		$userId = $m->getUserId();
		$data = $m->getOrderInfoAndAppr($userId);
		$data['shopName'] = model('shops')->getShopName($oId);
		$data['oId'] = $oId;
		$data['domain'] = $this->domain();
		return json_encode(WSTReturn('请求成功', 1, $data));
	}
	
	/**
	 * 用户取消订单
	 */
	public function cancellation(){
		$m = new M();
		$userId = $m->getUserId();
		$rs = $m->cancel($userId);
		return json_encode($rs);
	}
   
	/**
	 * 用户拒收订单
	 */
	public function reject(){
		$m = new M();
		$userId = $m->getUserId();
		$rs = $m->reject((int)$userId);
		return json_encode($rs);
	}

	/**
	* 用户退款
	*/
	public function getRefund(){
		$m = new M();
		$rs = $m->getMoneyByOrder((int)input('id'));
		return json_encode(WSTReturn('请求成功',1,$rs));
	}




	/*********************************************** 商家操作订单 ************************************************************/


	/**
	* 商家-订单列表
	*/
	public function getSellerOrderList(){
		/* 
		 	-3:拒收、退款列表
			-2:待付款列表 
			-1:已取消订单
			 0: 待发货
			1,2:待评价/已完成
		*/
		$type = input('param.type');
		$express = false;// 快递公司数据
		$status = [];
		switch ($type) {
			case 'waitPay':
				$status=-2;
				break;
			case 'waitReceive':
				$status=1;
				break;
			case 'waitDelivery':
				$status=0;
				$express=true;
				break;
			case 'finish': 
				$status=2;
				break;
			case 'abnormal': // 退款/拒收 与取消合并
				$status=[-1,-3];
				break;
			default:
				$status=[-5,-4,-3,-2,-1,0,1,2];
				$express=true;
				break;
		}
		$m = new M();
		$userId = $m->getUserId();
		$shopId = (int)$m->getShopId($userId);

		$rs = $m->shopOrdersByPage($status, $shopId);

		foreach($rs['data'] as $k=>$v){
			// 删除无用字段
			WSTUnset($rs['data'][$k],'goodsMoney,totalMoney,deliverType,deliverMoney,orderSrc,createTime,payTypeName,isRefund,userAddress,userName,deliverTypeName');
			// 判断是否退款
			if(in_array($v['orderStatus'],[-1,-3]) && ($v['payType']==1) && ($v['isPay']==1) ){
				$rs['data'][$k]['status'] .= ($v['isRefund']==1)?'(已退款)':'(未退款)';
			}
			if(!empty($v['list'])){
				foreach($v['list'] as $k1=>$v1){
					$rs['data'][$k]['list'][$k1]['goodsImg'] = $v1['goodsImg'];
				}
			}
		}
		// 获取域名,用于显示图片
		$rs['domain'] = $this->domain();
		// 快递公司数据
		if($express)$rs['express'] = model('Express')->listQuery();

		if(empty($rs['data']))return json_encode(WSTReturn('没有相关订单',-1));
		return json_encode(WSTReturn('请求成功', 1, $rs));
	}

	/**
	 * 商家发货
	 */
	public function deliver(){
		$m = new M();
		$userId = (int)$m->getUserId();
		$shopId = (int)$m->getShopId($userId);
		$rs = $m->deliver($userId, $shopId);

		return json_encode($rs);
	}
	/**
	 * 商家修改订单价格
	 */
	public function editOrderMoney(){
		$m = new M();
		$userId = (int)$m->getUserId();
		$shopId = (int)$m->getShopId($userId);
		$rs = $m->editOrderMoney($userId, $shopId);

		return json_encode($rs);
	}
	/**
	 * 商家-操作退款
	 */
	public function toShopRefund(){
		$rs = model('OrderRefunds')->getRefundMoneyByOrder((int)input('id'));
		return json_encode(WSTReturn('请求成功', 1, $rs));
	}
	
	
}
