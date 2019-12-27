<?php
namespace wstmart\common\model;
use think\Db;
use Env;
use think\Loader;
use wstmart\common\model\LogSms;
use wstmart\app\service\Users as ASUser;
use wstmart\common\exception\AppException as AE;
use wstmart\common\model\Pintuans as P;
use wstmart\common\service\Pintuan as SP;
use wstmart\common\model\UserAddress as UA;
use wstmart\app\service\Orders as O;
use wstmart\common\model\Coupons as C;
use wstmart\common\model\WxPayOrders as WPO;
use wstmart\common\service\WxTemplateNotify as WTN;
use wstmart\common\service\News;
use wstmart\common\helper\Phpexcel;

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
 * 订单业务处理类
 */
class Orders extends Base{
	protected $pk = 'orderId';
    public function getOrderById($orderId)
    {
        $order = $this->where('orderId', $orderId)->find();
        if (empty($order)) {
            throw AE::factory(AE::ORDER_NOT_EXISTS);
        }
        return $order;
    }

    public function getRefundOrder($orderId, $userId)
    {
        $order = Db::name('orders')->alias('o')
            ->join('__ORDER_REFUNDS__ orf','orf.orderId=o.orderId','left')
            ->join('__SHOPS__ s','o.shopId=s.shopId','left')
            ->where([['o.orderStatus','in',[-3,-1]],['o.orderId','=',$orderId],['o.userId','=',$userId],['isRefund','=',0]])
            ->field('o.orderId,s.userId,o.shopId,o.orderStatus,o.orderNo,o.realTotalMoney,o.isPay,o.payType,o.useScore,orf.id refundId')
            ->find();
        return $order;
    }

    public function getOrderInfoByOrderNo($orderNo)
    {
        $order = $this->where('orderNo', $orderNo)->find();
        if (empty($order)) {
            throw AE::factory(AE::ORDER_NOT_EXISTS);
        }
        return $order;
    }

    public function getOrderGoodsByOrderId($orderId)
    {
        $orderGoods = DB::name('order_goods')->where('orderId', $orderId)->find();
        if (empty($orderGoods)) {
            throw AE::factory(AE::ORDER_GOODS_NOT_EXISTS);
        }
        return $orderGoods;
    }

    public function getOrderByOrderNo($where)
    {
        $arr = DB::name('orders')->alias('o')
            ->join('order_goods og', 'og.orderId=o.orderId', 'left')
            ->where($where)
            ->field('og.goodsName, o.createTime, o.orderStatus, o.realTotalMoney, o.isPay, o.orderId, o.orderNo')
            ->find();
        if (empty($arr)) {
            throw AE::factory(AE::ORDER_NOT_EXISTS);
        }
        return $arr;
    }

    public function getOrderAndGoodsInfo($where)
    {
        $arr = DB::name('orders')->alias('o')
            ->join('order_goods og', 'og.orderId=o.orderId', 'left')
            ->where($where)
            ->field('og.id, og.goodsName, og.goodsSpecId, og.goodsId, o.shopId, o.createTime, o.orderStatus, o.realTotalMoney, o.isPay, o.orderId, o.isAppraise')
            ->find();
        return $arr;
    }

    public function getWechatPayOrderInfo($where)
    {
        $arr = DB::name('wx_pay_orders')->alias('wpo')
            ->join('orders o', 'wpo.orderNo=o.orderNo', 'left')
            ->where($where)
            ->where('wpo.isPay', 1)
            ->where('o.isPay', 1)
            ->field('wpo.type, wpo.targetId, wpo.appid, wpo.mch_id, wpo.out_trade_no, wpo.transaction_id, wpo.total_fee, wpo.isPay, wpo.trade_type, wpo.time_end, o.orderNo, o.orderId, o.payFrom, o.isPintuan')
            ->find();
        if (empty($arr)) {
            throw AE::factory(AE::ORDER_NOT_EXISTS);
        }
        return $arr;
    }

    public function getList($userId, $offset, $pageSize, $orderStatus = null)
    {
        $db = $this->alias('o')
            ->leftJoin('order_goods og', 'o.orderId=og.orderId')
            ->leftJoin('goods g', 'og.goodsId=g.goodsId')
            ->where('o.userId', $userId)
            ->whereNotIn('o.orderStatus', [-6,-1])
        //    ->where('(o.isPintuan = 0 and o.isPay = 1) or (o.isPintuan = 1 and o.isPay = 1) or (o.isPintuan = 0 and o.isPay = 0)')
            ->where('o.dataFlag', 1);
        if ($orderStatus !== null) {
            $db->where('o.orderStatus', $orderStatus)
                ->where('o.afterSaleStatus', '<>', 2);
            if ($orderStatus == 2) {
                $db->where('o.isAppraise', 0);
            }
        }
        $db1 = clone $db;
        $total = $db->count();
        $list = $db1->field('g.goodsId,g.goodsName,g.goodsImg,g.littleDesc,og.goodsSpecNames,og.goodsNum,o.orderNo,
        o.realTotalMoney,o.deliverMoney,o.orderStatus,o.afterSaleStatus,o.orderId,o.userId,o.isPintuan,o.isAppraise,
        o.noticeDeliver')
            ->limit(($offset - 1) * $pageSize, $pageSize)
            ->order('o.createTime', 'desc')
            ->select();
        return [
            'total' => $total,
            'list' => $list,
            'offset' => $offset,
            'pageSize' => $pageSize
        ];
    }

    public function orderInfo($orderId)
    {
        $info = $this->alias('o')
            ->leftJoin('order_goods og', 'og.orderId=o.orderId')
            ->leftJoin('goods g', 'og.goodsId=g.goodsId')
            ->leftJoin('users u', 'u.userId=o.userId')
            ->leftJoin('express e', 'e.expressId=o.expressId')
            ->leftJoin('order_refunds or', 'or.orderId=o.orderId')
            ->field('o.orderId,o.orderNo,o.orderStatus,o.isAppraise,o.noticeDeliver,o.afterSaleStatus,o.createTime,
            o.payFrom,o.goodsMoney,o.deliverMoney,o.couponsValue,o.realTotalMoney,o.payTime,o.deliveryTime,
            o.expressNo,e.expressName,e.expressCode,e.expressPhone,o.isPintuan,o.userName,o.userPhone,o.userAddress,u.userId,
            g.goodsId,g.goodsName,g.goodsImg,g.littleDesc,og.goodsSpecNames,og.goodsNum,og.goodsPrice,
            or.id refundId')
            ->where('o.orderId', $orderId)
            ->where('o.dataFlag', 1)
            ->find();
        if (empty($info)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        return $info;
    }

    public function getAfterSaleList($userId, $offset, $pageSize, $refundStatus = null)
    {
        $db = DB::name('order_refunds')->alias('or')
            ->leftJoin('orders o', 'or.orderId=o.orderId')
            ->leftJoin('order_goods og', 'o.orderId=og.orderId')
            ->leftJoin('goods g', 'og.goodsId=g.goodsId')
            ->where('or.refundTo', $userId)
            ->where('o.dataFlag', 1)
            ->where('or.refundStatus', '<>', 3);

        if ($refundStatus !== null) {
            $db->where('or.refundStatus',$refundStatus);
        }
        $db1 = clone $db;
        $total = $db->count();
        $list = $db1->field('g.goodsName,g.goodsImg,g.littleDesc,og.goodsSpecNames,og.goodsNum,
        o.realTotalMoney,o.deliverMoney,o.orderId,o.afterSaleStatus,or.backMoney,or.id refundId')
            ->limit(($offset - 1) * $pageSize, $pageSize)
            ->order('o.createTime', 'desc')
            ->select();
        return [
            'total' => $total,
            'list' => $list,
            'offset' => $offset,
            'pageSize' => $pageSize
        ];
    }

    /**
	 * 快速下单(原版)
	 */
	public function quickSubmit($orderSrc = 0, $uId=0){
		$deliverType = 0;
		$isInvoice = ((int)input('post.isInvoice')!=0)?1:0;
		$invoiceClient = ($isInvoice==1)?input('post.invoiceClient'):'';
		$payType = 1;
		$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
		if($userId==0)return WSTReturn('下单失败，请先登录');
		$isUseScore = (int)input('isUseScore');
		$useScore = (int)input('useScore');
		//检测购物车
		$carts = model('common/carts')->getQuickCarts($uId);
		if(empty($carts['carts']))return WSTReturn("请选择要购买的商品");
		//使用积分金额不能超过商品金额
		$tempScoreMoney = WSTScoreToMoney($carts['goodsTotalMoney']-$carts['promotionMoney'],true);
		$useScore = ($useScore>$tempScoreMoney)?$tempScoreMoney:$useScore;
		$orderScoreMap = [];
		$scoreMoney = $this->getOrderScoreMoney($isUseScore,$useScore,$uId);
		//生成订单
		Db::startTrans();
		try{
			//提交订单前执行钩子
			hook('beforeSubmitOrder',['carts'=>$carts,"payType"=>$payType]);
			$shopOrder = current($carts['carts']);
			$goods = $shopOrder['list'][0];
			if($goods['goodsStock']<$goods['cartNum'])return WSTReturn("下单失败，商品库存不足");
			//给用户分配卡券
			$cards = model('GoodsVirtuals')->where(['goodsId'=>$goods['goodsId'],'dataFlag'=>1,'shopId'=>$goods['shopId'],'isUse'=>0])->lock(true)->limit($goods['cartNum'])->select();
			if(count($cards)<$goods['cartNum'])return WSTReturn("下单失败，商品库存不足");
			//修改库存
			Db::name('goods')->where('goodsId',$goods['goodsId'])->update([
				'goodsStock'=>['exp','goodsStock-'.$goods['cartNum']],
                'saleNum'=>['exp','saleNum+'.$goods['cartNum']],
			]);
			$orderunique = WSTOrderQnique();
			$orderNo = WSTOrderNo(); 
			$orderScore = 0;
			//创建订单
			$order = [];
			$order['orderNo'] = $orderNo;
			$order['orderType'] = 1;
			$order['areaId'] = 0;
			$order['userName'] = '';
			$order['userAddress'] = '';
			$order['userId'] = $userId;
			$order['shopId'] = $shopOrder['shopId'];
			$order['payType'] = $payType;
			$order['goodsMoney'] = $shopOrder['goodsMoney'];
			$order['deliverType'] = $deliverType;
			$order['deliverMoney'] = 0;
			$order['totalMoney'] = $order['goodsMoney']+$order['deliverMoney'];
			$order['scoreMoney'] = 0;
			$order['useScore'] = 0;
			if($scoreMoney['useMoney']>0){
				$order['scoreMoney'] = $scoreMoney['useMoney'];
				$order['useScore'] = $scoreMoney['useScore'];
			}
			$order['realTotalMoney'] = WSTPositiveNum($order['totalMoney'] - $order['scoreMoney'] - $shopOrder['promotionMoney']);
			$order['needPay'] = $order['realTotalMoney'];
			if($order['needPay']>0){
	            $order['orderStatus'] = -2;//待付款
				$order['isPay'] = 0; 
			}else{
				$order['orderStatus'] = 0;//待发货
				$order['isPay'] = 1;
				$order['payFrom'] = 'others';
			}
			//积分
			$orderScore = 0;
			//如果开启下单获取积分则有积分
			if(WSTConf('CONF.isOrderScore')==1){
				$orderScore = round($order['goodsMoney'],0);
			}
			$order['orderScore'] = $orderScore;
			$order['isInvoice'] = $isInvoice;
			if($isInvoice==1){
				$order['invoiceJson'] = model('invoices')->getInviceInfo((int)input('param.invoiceId'));// 发票信息
			    $order['invoiceClient'] = $invoiceClient;
			}else{
				$order['invoiceJson'] = '';
				$order['invoiceClient'] = '';
			}
			$order['orderRemarks'] = input('post.remark_'.$shopOrder['shopId']);
			$order['orderunique'] = $orderunique;
			$order['orderSrc'] = $orderSrc;
			$order['dataFlag'] = 1;
			$order['payRand'] = 1;
			$order['createTime'] = date('Y-m-d H:i:s');
			//创建订单前执行钩子
			hook('beforeInsertOrder',['order'=>&$order,'carts'=>$carts]);
			$result = $this->data($order,true)->isUpdate(false)->allowField(true)->save($order);
			if(false !== $result){
				$orderId = $this->orderId;
				//标记虚拟卡券为占用状态
				$goodsCards = [];
			    foreach ($cards as $key => $card) {
				    model('GoodsVirtuals')->where('id',$card['id'])->update(['isUse'  => 1,'orderId' => $orderId,'orderNo' => $orderNo]);
				    $goodsCards[] = ['cardId'=>$card->id];
			    }
				//创建订单商品记录
				$orderGgoods = [];
				$orderGoods['orderId'] = $orderId;
				$orderGoods['goodsId'] = $goods['goodsId'];
				$orderGoods['goodsNum'] = $goods['cartNum'];
				$orderGoods['goodsPrice'] = $goods['shopPrice'];
				$orderGoods['goodsSpecId'] = 0;
				$orderGoods['goodsSpecNames'] = '';		
				$orderGoods['goodsName'] = $goods['goodsName'];
				$orderGoods['goodsImg'] = $goods['goodsImg'];
				$orderGoods['commissionRate'] = WSTGoodsCommissionRate($goods['goodsCatId']);
				$orderGoods['goodsCode'] = '';
				$orderGoods['goodsType'] = 1;
				$orderGoods['extraJson'] = json_encode($goodsCards);
				$orderGoods['promotionJson'] = '';
				$orderTotalGoods = [];
				$orderTotalGoods[] = $orderGoods;
				//创建订单商品前执行钩子
			    hook('beforeInsertOrderGoods',['orderId'=>$orderId,'orderGoods'=>&$orderTotalGoods,'carts'=>$carts]);
				Db::name('order_goods')->insertAll($orderTotalGoods);
				//计算订单佣金
				$commissionFee = 0;
				if((float)$orderGoods['commissionRate']>0){
					$commissionFee += round($goods['shopPrice']*1*$orderGoods['commissionRate']/100,2);
				}
				$this->where('orderId',$orderId)->update(['commissionFee'=>$commissionFee]);
				//提交订单后执行钩子
				hook('afterSubmitOrder',['orderId'=>$orderId]);
				//创建积分流水--如果有抵扣积分就肯定是开启了支付支付
				if($order['useScore']>0){
					$score = [];
					$score['userId'] = $userId;
					$score['score'] = $order['useScore'];
					$score['dataSrc'] = 1;
					$score['dataId'] = $orderId;
					$score['dataRemarks'] = "交易订单【".$orderNo."】使用积分".$order['useScore']."个";
					$score['scoreType'] = 0;
					model('UserScores')->add($score);
				}	
				//建立订单记录
				$logOrder = [];
				$logOrder['orderId'] = $orderId;
				$logOrder['orderStatus'] = -2;
				$logOrder['logContent'] = "下单成功，等待用户支付";
				$logOrder['logUserId'] = $userId;
				$logOrder['logType'] = 0;
				$logOrder['logTime'] = date('Y-m-d H:i:s');
				Db::name('log_orders')->insert($logOrder);
				//等待支付-给店铺增加提示消息
			    $tpl = WSTMsgTemplates('ORDER_SUBMIT');
		        if( $tpl['tplContent']!='' && $tpl['status']=='1'){
		            $find = ['${ORDER_NO}'];
		            $replace = [$orderNo];
		           
		        	$msg = array();
		            $msg["shopId"] = $shopOrder['shopId'];
		            $msg["tplCode"] = $tpl["tplCode"];
		            $msg["msgType"] = 1;
		            $msg["content"] = str_replace($find,$replace,$tpl['tplContent']);
		            $msg["msgJson"] = ['from'=>1,'dataId'=>$orderId];
		            model("common/MessageQueues")->add($msg);
		        }
                //判断是否需要发送管理员短信
	            $tpl = WSTMsgTemplates('PHONE_ADMIN_SUBMIT_ORDER');
	            if((int)WSTConf('CONF.smsOpen')==1 && (int)WSTConf('CONF.smsSubmitOrderTip')==1 &&  $tpl['tplContent']!='' && $tpl['status']=='1'){
					$params = ['tpl'=>$tpl,'params'=>['ORDER_NO'=>$orderNo]];
					$staffs = Db::name('staffs')->where([['staffId','in',explode(',',WSTConf('CONF.submitOrderTipUsers'))],['staffStatus','=',1],['dataFlag','=',1]])->field('staffPhone')->select();
					for($i=0;$i<count($staffs);$i++){
						if($staffs[$i]['staffPhone']=='')continue;
						$m = new LogSms();
				        $rv = $m->sendAdminSMS(0,$staffs[$i]['staffPhone'],$params,'submit','');
				    }
	            }
		        //微信消息
		        if((int)WSTConf('CONF.wxenabled')==1){
		            $params = [];
		            $params['ORDER_NO'] = $orderNo;
	                $params['ORDER_TIME'] = date('Y-m-d H:i:s');             
	                $goodsNames = $goods['goodsName']."*".$goods['cartNum'];
		            $params['GOODS'] = $goodsNames;
		            $params['MONEY'] = $order['realTotalMoney'] + $order['scoreMoney'];
		            $params['ADDRESS'] = '';
		            $params['PAY_TYPE'] = WSTLangPayType($order['payType']);
	                
		            $msg = array();
					$tplCode = "WX_ORDER_SUBMIT";
					$msg["shopId"] = $shopOrder['shopId'];
		            $msg["tplCode"] = $tplCode;
		            $msg["msgType"] = 4;
		            $msg["paramJson"] = ['CODE'=>$tplCode,'URL'=>Url('wechat/orders/sellerorder','',true,true),'params'=>$params];
		            $msg["msgJson"] = "";
		            model("common/MessageQueues")->add($msg);
		            //判断是否需要发送给管理员消息
		            if((int)WSTConf('CONF.wxSubmitOrderTip')==1){
		                $params = [];
			            $params['ORDER_NO'] = $orderNo;
		                $params['ORDER_TIME'] = date('Y-m-d H:i:s');             
		                $goodsNames = $goods['goodsName']."*".$goods['cartNum'];
			            $params['GOODS'] = $goodsNames;
			            $params['MONEY'] = $order['realTotalMoney'] + $order['scoreMoney'];
			            $params['ADDRESS'] = '';
			            $params['PAY_TYPE'] = WSTLangPayType($order['payType']);
			            WSTWxBatchMessage(['CODE'=>'WX_ADMIN_ORDER_SUBMIT','userType'=>3,'userId'=>explode(',',WSTConf('CONF.submitOrderTipUsers')),'params'=>$params]);
		            }
		        }
				//虚拟商品支付完成-立即发货
				if($order['needPay']==0){
					$logOrder = [];
					$logOrder['orderId'] = $orderId;
					$logOrder['orderStatus'] = 0;
					$logOrder['logContent'] = "订单已支付，下单成功";
					$logOrder['logUserId'] = $userId;
					$logOrder['logType'] = 0;
					$logOrder['logTime'] = date('Y-m-d H:i:s');
					Db::name('log_orders')->insert($logOrder);
					$this->handleVirtualGoods($orderId);
				}
			}
			//删除session的购物车商品
			session('TMP_CARTS',null);
			Db::commit();
			return WSTReturn("提交订单成功", 1,$orderunique);
		}catch (\Exception $e) {
            Db::rollback();
            return WSTReturn('提交订单失败',-1);
        }
	}

    /**
     * 生成订单
     * @chenxj
     */
    public function quickSubmitOrder($userId, $goodsId, $couponsId, $goodsNum, $data, $addressInfo, $goodsShopInfo, $isPintuan, $tuanId, $tuanNo, $pid){
        Db::startTrans();
        try{
            //生成订单
            $data['orderNo'] = $this->createOrderNo($goodsShopInfo['goodsCatIdPath']).getRand(6);
            $result = $this->insertOrder($userId, $data, $addressInfo, $goodsShopInfo, $pid);
            //如果是拼团，添加数据到拼团表
            $pinTuanNo = '';
            if (false !== $result){
                $orderId = $this->orderId;
                if (preg_match('/^1_\d{1,}/', $pid)) {
                    $this->addXiaodianOrder($pid, $orderId, $data['realTotalMoney']);
                }
                //加入商品订单
                $this->insertPrderGoods($orderId, $data, $goodsShopInfo);
                if ($isPintuan==1) {
                    $sp = new SP();
                    //小店过来的单人购买享受拼团价
                    if (!empty($pid)) {
                        $pidChannel = explode('_', $pid);
                        if (!in_array((int)$pidChannel[0],[1,2])) {
                            $pinTuanNo = $sp->afterCreateOrder($orderId, $tuanId, $tuanNo);
                        }
                    }else {
                        $pinTuanNo = $sp->afterCreateOrder($orderId, $tuanId, $tuanNo);
                    }
                }
                if ($data['specId']>0) {
                    Db::name('goods_specs')->where('id',$data['specId'])->setInc('saleNum', $goodsNum);
                    Db::name('goods_specs')->where('id',$data['specId'])->setDec('specStock', $goodsNum);
                }
                Db::name('goods')->where('goodsId',$goodsId)->setInc('saleNum', $goodsNum);
                Db::name('goods')->where('goodsId',$goodsId)->setDec('goodsStock', $goodsNum);

                if ($couponsId>0) {
                    $this->updateCouponsUseStatus($couponsId, $userId, 1, $this->orderNo, date('Y-m-d H:i:s'));
                }
            }
            $msg['orderId'] = $this->orderId;
            $msg['orderNo'] = $data['orderNo'];
            $msg['tuanNo'] = $pinTuanNo;
            Db::commit();
            return $msg;
        }catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    protected function addXiaodianOrder($pid, $orderId, $realTotalMoney)
    {
        $sid = substr($pid, 2);
        $orderInfo = [
            'sid' => $sid,
            'pid' => $pid,
            'orderId' => $orderId,
            'isValid' => 1,
            'isSettlement' => 0,
            'commission' => bcmul(config('web.commission_ratio'), $realTotalMoney, 2),
        ];
        DB::name('xiaodian_orders')->insert($orderInfo);
    }

    public function updateCouponsUseStatus($couponsId, $userId, $isUse, $orderNo, $time='')
    {
        if ($couponsId<0) return true;
        $couponData = [
            'isUse'=>$isUse,
            'orderNo'=>$orderNo,
            'useTime'=>$time
        ];
        $c = new C();
        $c->updateCouponUserUseStatus($couponsId, $userId, $couponData);
        return true;
    }

    /**
     * 加入订单
     */
    public function insertOrder($userId, $data, $addressInfo, $goodsShopInfo, $pid)
    {
        $isInvoice = ((int)getInput('post.isInvoice')!=0)?1:0;
        if ($isInvoice==1) {
            $invoiceClient = getInput('post.invoiceClient', '');
        }
        $orderunique = WSTOrderQnique();
        $orderScore = 0;
        //创建订单
        $order = [];
        $order['orderNo'] = $data['orderNo'];
        $order['userId'] = $userId;
        $order['shopId'] = $goodsShopInfo['shopId'];
        $order['orderStatus'] = -2;//待付款
        $order['goodsMoney'] = bcmul($data['goodsMoney'],$data['goodsNum'],2);
        $order['deliverType'] = $data['deliverType'];
        $order['deliverMoney'] = $data['deliverMoney'];
        $order['totalMoney'] = bcadd($order['goodsMoney'],$data['deliverMoney'],2);
        $order['payType'] = 1;
        //小店过来的单人购买享受拼团价，但是订单里面不是拼团
        if (!empty($pid)) {
            $pidChannel = explode('_', $pid);
            $order['isPintuan'] = !in_array((int)$pidChannel[0],[1,2]) ? $data['isPintuan'] : 0;
        }else {
            $order['isPintuan'] = $data['isPintuan'];
        }
        $order['areaId'] = $addressInfo['areaId'];
        $order['areaIdPath'] = $addressInfo['areaIdPath'];
        $order['userName'] = $addressInfo['userName'];
        $order['userAddress'] = $addressInfo['addressName'].' '.$addressInfo['userAddress'];
        $order['userPhone'] = $addressInfo['userPhone'];
        $order['orderType'] = 1;
        $order['scoreMoney'] = 0;
        $order['useScore'] = 0;
        $order['realTotalMoney'] = $data['realTotalMoney'];
        $order['needPay'] = $order['realTotalMoney'];
        if ($data['couponsId']>0) {
            $order['couponsValue'] = $data['couponValue'];
            $order['userCouponId'] = $data['couponsId'];
        }
        $order['orderSrc'] = config('web.orderSrcType.'.ASUser::getUserByCache()['client']);
        //if($order['needPay']>=0){
        $order['isPay'] = 0;
        //}else{
            //$order['orderStatus'] = 0;//待发货
            //$order['isPay'] = 1;
            //$order['payFrom'] = 'others';
        //}
        //积分
        $orderScore = 0;
        //如果开启下单获取积分则有积分
//            if(WSTConf('CONF.isOrderScore')==1){
//                $orderScore = round($order['goodsMoney'],0);
//            }
        $order['orderScore'] = $orderScore;
        $order['isInvoice'] = $isInvoice;
        if($isInvoice==1){
            $order['invoiceJson'] = model('invoices')->getInviceInfo((int)input('param.invoiceId'));// 发票信息
            $order['invoiceClient'] = $invoiceClient;
        }else{
            $order['invoiceJson'] = '';
            $order['invoiceClient'] = '';
        }
        $order['orderRemarks'] = '';
        $order['orderunique'] = $orderunique;
        $order['dataFlag'] = 1;
        $order['payRand'] = 1;
        $order['createTime'] = date('Y-m-d H:i:s');
        $order['pid'] = $pid;
        if ($userId) {
            $user = (new Users())->getUserById($userId);
            if (!empty($user->wxUnionId)) {
                $fanliUser = DB::name('fanli_shopkeepers')->where('unionId', $user->wxUnionId)->find();
                if (!empty($fanliUser)) {
                    $order['pid'] = $fanliUser['pid'];
                }
            }
        }
        $order['milliCreateTime'] = bcmul(1000, microtime(true));
        //创建订单前执行钩子
        //hook('beforeInsertOrder',['order'=>&$order,'carts'=>$carts]);
        $result = $this->data($order,true)->isUpdate(false)->allowField(true)->save($order);
        if (!$result) {
            throw AE::factory(AE::ORDER_CREATE_FAIL);
        }
        return $result;
    }

    /*
     * 生成订单号
     * 规则：DD（订单）+商品分类编号（9位）+时间（年月日6位）+流水号（6位）
     * 例子：DDF01S01T01180704000001
     */
    public function createOrderNo($goodsCatIdPath)
    {
        $goodsCatId = explode('_', $goodsCatIdPath);
        $time = substr(date('Ymd',time()),2);
        $orderNo = 'DDF'.$goodsCatId[0].'S'.$goodsCatId[1].'T'.$goodsCatId[2].$time;
        return $orderNo;
    }

    /*
     * 加入订单商品表与计算佣金
     */
    public function insertPrderGoods($orderId, $data, $goodsShopInfo)
    {
        //创建订单商品记录
        $orderGgoods = [];
        $orderGoods['orderId'] = $orderId;
        $orderGoods['goodsId'] = $data['goodsId'];
        $orderGoods['goodsNum'] = $data['goodsNum'];
        $orderGoods['goodsPrice'] = $data['goodsMoney'];
        $orderGoods['goodsSpecId'] = $data['specId'];
        $specId = model('common/Goods')->getGoodsSpec(['id'=>$data['specId'],'goodsId'=>$data['goodsId']]);
        $specArr = explode(':', $specId['specIds']);
        $o = new O();
        $orderGoods['goodsSpecNames'] = $o->getSpecName($specArr);
        $orderGoods['goodsName'] = $goodsShopInfo['goodsName'];
        $orderGoods['goodsImg'] = $goodsShopInfo['goodsImg'];
        $orderGoods['commissionRate'] = 0.00;
        $orderGoods['goodsCode'] = '';
        $orderGoods['goodsType'] = 0;
        $orderGoods['extraJson'] = '';
        $orderGoods['promotionJson'] = '';
        $orderTotalGoods = [];
        $orderTotalGoods[] = $orderGoods;
        //创建订单商品前执行钩子
        //hook('beforeInsertOrderGoods',['orderId'=>$orderId,'orderGoods'=>&$orderTotalGoods,'carts'=>$carts]);
        $result = Db::name('order_goods')->insertAll($orderTotalGoods);
        if (!$result) {
            throw AE::factory(AE::ORDER_CREATE_FAIL);
        }
        //计算订单佣金
        $commissionFee = 0;
        if((float)$orderGoods['commissionRate']>0){
            $commissionFee += bcdiv(bcmul(bcmul($goodsShopInfo['shopPrice'],$data['goodsNum'],2),$orderGoods['commissionRate'],2),100,2);
        }
        $this->where('orderId',$orderId)->update(['commissionFee'=>$commissionFee]);
        return $result;
    }

    /*
     * 记录订单日志
     */
    public function logOrder($orderId, $userId)
    {
        //建立订单记录
        $logOrder = [];
        $logOrder['orderId'] = $orderId;
        $logOrder['orderStatus'] = -2;
        $logOrder['logContent'] = "下单成功，等待用户支付";
        $logOrder['logUserId'] = $userId;
        $logOrder['logType'] = 0;
        $logOrder['logTime'] = date('Y-m-d H:i:s');
       return Db::name('log_orders')->insert($logOrder);
    }

	/**
	 * 正常订单
	 */
	public function submit($orderSrc = 0, $uId=0){
		$addressId = (int)input('post.s_addressId');
		$deliverType = ((int)input('post.deliverType')!=0)?1:0;
		$isInvoice = ((int)input('post.isInvoice')!=0)?1:0;
		$invoiceClient = ($isInvoice==1)?input('post.invoiceClient'):'';
		$payType = ((int)input('post.payType')!=0)?1:0;
		$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
		$isUseScore = (int)input('isUseScore');
		$useScore = (int)input('useScore');
		if($userId==0)return WSTReturn('下单失败，请先登录');
		//检测购物车
		$carts = model('common/carts')->getCarts(true, $userId);
		if(empty($carts['carts']))return WSTReturn("下单失败，请选择有效的库存商品");
		if($deliverType==0){// 配送方式为快递，必须有用户地址
			//检测地址是否有效
			$address = Db::name('user_address')->where(['userId'=>$userId,'addressId'=>$addressId,'dataFlag'=>1])->find();
			if(empty($address)){
				return WSTReturn("无效的用户地址");
			}
		    $areaIds = [];
	        $areaMaps = [];
	        $tmp = explode('_',$address['areaIdPath']);
	        $address['areaId2'] = $tmp[1];//记录配送城市
	        foreach ($tmp as $vv){
	         	if($vv=='')continue;
	         	if(!in_array($vv,$areaIds))$areaIds[] = $vv;
	        }
	        if(!empty($areaIds)){
		         $areas = Db::name('areas')->where([['areaId','in',$areaIds],['dataFlag','=',1]])->field('areaId,areaName')->select();
		          foreach ($areas as $v){
		         	 $areaMaps[$v['areaId']] = $v['areaName'];
		         }
		         $tmp = explode('_',$address['areaIdPath']);
		         $areaNames = [];
			     foreach ($tmp as $vv){
		         	 if($vv=='')continue;
		         	 $areaNames[] = $areaMaps[$vv];
		         	 $address['areaName'] = implode('',$areaNames);
		         }
	        }
			$address['userAddress'] = $address['areaName'].$address['userAddress'];
			WSTUnset($address, 'isDefault,dataFlag,createTime,userId');
		}else{
			$address = [];
			$address['areaId'] = 0;
			$address['userName'] = '';
			$address['userAddress'] = '';
		}
		//计算出每个订单应该分配的金额和积分
		$orderScoreMoney = $this->allocScoreMoney($carts,$isUseScore,$useScore, $uId);
		//生成订单
		Db::startTrans();
		try{
			//提交订单前执行钩子
			hook('beforeSubmitOrder',['carts'=>$carts,"payType"=>$payType]);
			$orderunique = WSTOrderQnique();
			foreach ($carts['carts'] as $ckey =>$shopOrder){
				$orderNo = WSTOrderNo(); 
				$orderScore = 0;
				//创建订单
				$order = [];
				$order = array_merge($order,$address);
				$order['orderNo'] = $orderNo;
				$order['userId'] = $userId;
				$order['shopId'] = $shopOrder['shopId'];
				$order['payType'] = $payType;
				$order['goodsMoney'] = $shopOrder['goodsMoney'];
				//计算运费和总金额
				$order['deliverType'] = $deliverType;
				if($shopOrder['isFreeShipping']){
                    $order['deliverMoney'] = 0;
				}else{
					$order['deliverMoney'] = ($deliverType==1)?0:WSTOrderFreight($shopOrder['shopId'],$order['areaId2']);				
				}
				$order['totalMoney'] = $order['goodsMoney']+$order['deliverMoney'];
                //积分支付-计算分配积分和金额
                $shopOrderMoney = $orderScoreMoney[$shopOrder['shopId']];
				$order['scoreMoney'] = $shopOrderMoney['useMoney'];
				$order['useScore'] = $shopOrderMoney['useScore'];
				//实付金额要减去积分兑换的金额和店铺总优惠
				$order['realTotalMoney'] = WSTPositiveNum($order['totalMoney'] - $order['scoreMoney'] - $shopOrder['promotionMoney']);
				$order['needPay'] = $order['realTotalMoney'];
                if($payType==1){
                	if($order['needPay']>0){
                        $order['orderStatus'] = -2;//待付款
				        $order['isPay'] = 0; 
                	}else{
                        $order['orderStatus'] = 0;//待发货
				        $order['isPay'] = 1;
						$order['payFrom'] = 'others'; 
                	}
				}else{
					$order['orderStatus'] = 0;//待发货
					if($order['needPay']==0){
						$order['isPay'] = 1; 
						$order['payFrom'] = 'others';
					}
				}
				//积分
				$orderScore = 0;
				//如果开启下单获取积分则有积分
				if(WSTConf('CONF.isOrderScore')==1){
				    $orderScore = WSTMoneyGiftScore($order['goodsMoney']);
				}
				$order['orderScore'] = $orderScore;
				$order['isInvoice'] = $isInvoice;
				if($isInvoice==1){
					$order['invoiceJson'] = model('invoices')->getInviceInfo((int)input('param.invoiceId'),$uId);// 发票信息
					$order['invoiceClient'] = $invoiceClient;
				}else{
					$order['invoiceJson'] = '';// 发票信息
					$order['invoiceClient'] = '';
				}
				$order['orderRemarks'] = input('post.remark_'.$shopOrder['shopId']);
				$order['orderunique'] = $orderunique;
				$order['orderSrc'] = $orderSrc;
				$order['dataFlag'] = 1;
				$order['payRand'] = 1;
				$order['createTime'] = date('Y-m-d H:i:s');
				//创建订单前执行钩子
			    hook('beforeInsertOrder',['order'=>&$order,'carts'=>$carts]);
				$result = $this->data($order,true)->isUpdate(false)->allowField(true)->save($order);
				if(false !== $result){
					$orderId = $this->orderId;
					$orderTotalGoods = [];
					$commissionFee = 0;
					foreach ($shopOrder['list'] as $gkey =>$goods){
						//创建订单商品记录
						$orderGgoods = [];
						$orderGoods['orderId'] = $orderId;
						$orderGoods['goodsId'] = $goods['goodsId'];
						$orderGoods['goodsNum'] = $goods['cartNum'];
						$orderGoods['goodsPrice'] = $goods['shopPrice'];
						$orderGoods['goodsSpecId'] = $goods['goodsSpecId'];
						if(!empty($goods['specNames'])){
							$specNams = [];
							foreach ($goods['specNames'] as $pkey =>$spec){
								$specNams[] = $spec['catName'].'：'.$spec['itemName'];
							}
							$orderGoods['goodsSpecNames'] = implode('@@_@@',$specNams);
						}else{
							$orderGoods['goodsSpecNames'] = '';
						}
						$orderGoods['goodsName'] = $goods['goodsName'];
						$orderGoods['goodsImg'] = $goods['goodsImg'];
						$orderGoods['commissionRate'] = WSTGoodsCommissionRate($goods['goodsCatId']);
						$orderGoods['goodsCode'] = '';
						$orderGoods['goodsType'] = 0;
						$orderGoods['extraJson'] = '';
						$orderGoods['promotionJson'] = '';
						$orderTotalGoods[] = $orderGoods;
						//计算订单总佣金
                        if((float)$orderGoods['commissionRate']>0){
                        	$commissionFee += round($orderGoods['goodsPrice']*$orderGoods['goodsNum']*$orderGoods['commissionRate']/100,2);
                        }
						//修改库存
						if($goods['goodsSpecId']>0){
					        Db::name('goods_specs')->where('id',$goods['goodsSpecId'])->update([
					        	'specStock'=>['exp','specStock-'.$goods['cartNum']],
					        	'saleNum'=>['exp','saleNum+'.$goods['cartNum']]
					        ]);
						}
						Db::name('goods')->where('goodsId',$goods['goodsId'])->update([
							'goodsStock'=>['exp','goodsStock-'.$goods['cartNum']],
							'saleNum'=>['exp','saleNum+'.$goods['cartNum']],
						]);
					}
					//创建订单商品前执行钩子
			        hook('beforeInsertOrderGoods',['orderId'=>$orderId,'orderGoods'=>&$orderTotalGoods,'carts'=>$carts]);
					Db::name('order_goods')->insertAll($orderTotalGoods);
					//更新订单佣金
					$this->where('orderId',$orderId)->update(['commissionFee'=>$commissionFee]);
					//提交订单后执行钩子
					hook('afterSubmitOrder',['orderId'=>$orderId]);

					//创建积分流水--如果有抵扣积分就肯定是开启了支付支付
					if($order['useScore']>0){
						$score = [];
					    $score['userId'] = $userId;
					    $score['score'] = $order['useScore'];
					    $score['dataSrc'] = 1;
					    $score['dataId'] = $orderId;
					    $score['dataRemarks'] = "交易订单【".$orderNo."】使用积分".$order['useScore']."个";
					    $score['scoreType'] = 0;
					    model('UserScores')->add($score);
					}
                    
					//建立订单记录
					$logOrder = [];
					$logOrder['orderId'] = $orderId;
					$logOrder['orderStatus'] = ($payType==1 && $order['needPay']==0)?-2:$order['orderStatus'];
					$logOrder['logContent'] = ($payType==1)?"下单成功，等待用户支付":"下单成功";
					$logOrder['logUserId'] = $userId;
					$logOrder['logType'] = 0;
					$logOrder['logTime'] = date('Y-m-d H:i:s');
					Db::name('log_orders')->insert($logOrder);
					if($payType==1 && $order['needPay']==0){
						$logOrder = [];
						$logOrder['orderId'] = $orderId;
						$logOrder['orderStatus'] = 0;
						$logOrder['logContent'] = "订单已支付，下单成功";
						$logOrder['logUserId'] = $userId;
						$logOrder['logType'] = 0;
						$logOrder['logTime'] = date('Y-m-d H:i:s');
						Db::name('log_orders')->insert($logOrder);
					}
					//给店铺增加提示消息
					$tpl = WSTMsgTemplates('ORDER_SUBMIT');
	                if( $tpl['tplContent']!='' && $tpl['status']=='1'){
	                    $find = ['${ORDER_NO}'];
	                    $replace = [$orderNo];
	                    
	                	$msg = array();
			            $msg["shopId"] = $shopOrder['shopId'];
			            $msg["tplCode"] = $tpl["tplCode"];
			            $msg["msgType"] = 1;
			            $msg["content"] = str_replace($find,$replace,$tpl['tplContent']);
			            $msg["msgJson"] = ['from'=>1,'dataId'=>$orderId];
			            model("common/MessageQueues")->add($msg);
	                }
	                //判断是否需要发送管理员短信
	                $tpl = WSTMsgTemplates('PHONE_ADMIN_SUBMIT_ORDER');
	                if((int)WSTConf('CONF.smsOpen')==1 && (int)WSTConf('CONF.smsSubmitOrderTip')==1 &&  $tpl['tplContent']!='' && $tpl['status']=='1'){
						$params = ['tpl'=>$tpl,'params'=>['ORDER_NO'=>$orderNo]];
						$staffs = Db::name('staffs')->where([['staffId','in',explode(',',WSTConf('CONF.submitOrderTipUsers'))],['staffStatus','=',1],['dataFlag','=',1]])->field('staffPhone')->select();
						for($i=0;$i<count($staffs);$i++){
							if($staffs[$i]['staffPhone']=='')continue;
							$m = new LogSms();
				            $rv = $m->sendAdminSMS(0,$staffs[$i]['staffPhone'],$params,'submit','');
				        }
	                }
	                //微信消息
	                if((int)WSTConf('CONF.wxenabled')==1){
	                	$params = [];
	                	$params['ORDER_NO'] = $orderNo;
                        $params['ORDER_TIME'] = date('Y-m-d H:i:s');             
	                	$goodsNames = [];
	                	foreach ($shopOrder['list'] as $gkey =>$goods){
                            $goodsNames[] = $goods['goodsName']."*".$goods['cartNum'];
	                	}
	                	$params['GOODS'] = implode(',',$goodsNames);
	                	$params['MONEY'] = $order['realTotalMoney'] + $order['scoreMoney'];
	                	$params['ADDRESS'] = $order['userAddress']." ".$order['userName'];
	                	$params['PAY_TYPE'] = WSTLangPayType($order['payType']);
		            	
		                $msg = array();
						$tplCode = "WX_ORDER_SUBMIT";
						$msg["shopId"] = $shopOrder['shopId'];
			            $msg["tplCode"] = $tplCode;
			            $msg["msgType"] = 4;
			            $msg["paramJson"] = ['CODE'=>$tplCode,'URL'=>Url('wechat/orders/sellerorder','',true,true),'params'=>$params];
			            $msg["msgJson"] = "";
			            model("common/MessageQueues")->add($msg);

		                //判断是否需要发送给管理员消息
		                if((int)WSTConf('CONF.wxSubmitOrderTip')==1){
		                	$params = [];
		                	$params['ORDER_NO'] = $orderNo;
	                        $params['ORDER_TIME'] = date('Y-m-d H:i:s');             
		                	$goodsNames = [];
		                	foreach ($shopOrder['list'] as $gkey =>$goods){
	                            $goodsNames[] = $goods['goodsName']."*".$goods['cartNum'];
		                	}
		                	$params['GOODS'] = implode(',',$goodsNames);
		                	$params['MONEY'] = $order['realTotalMoney'] + $order['scoreMoney'];
		                	$params['ADDRESS'] = $order['userAddress']." ".$order['userName'];
		                	$params['PAY_TYPE'] = WSTLangPayType($order['payType']);
			            	WSTWxBatchMessage(['CODE'=>'WX_ADMIN_ORDER_SUBMIT','userType'=>3,'userId'=>explode(',',WSTConf('CONF.submitOrderTipUsers')),'params'=>$params]);
		                }
		            }
				}
			}
			//删除已选的购物车商品
			Db::name('carts')->where(['userId'=>$userId,'isCheck'=>1])->delete();
			Db::commit();
			return WSTReturn("提交订单成功", 1,$orderunique);
		}catch (\Exception $e) {
            Db::rollback();
            return WSTReturn('提交订单失败',-1);
        }
	}
	/**
	 * 计算订单可用积分和金额【积分支付使用】
	 */
	public function allocScoreMoney($carts,$isUseScore,$useScore, $uId=0){
		//使用积分金额不能超过商品金额
		$tempScoreMoney = WSTScoreToMoney($carts['goodsTotalMoney']-$carts['promotionMoney'],true);
		$useScore = ($useScore>$tempScoreMoney)?$tempScoreMoney:$useScore;
		$orderScoreMap = [];
		$scoreMoney = $this->getOrderScoreMoney($isUseScore, $useScore, $uId);
		$allocOrderMoney = $scoreMoney['useMoney'];//积分可兑换的总金额
		$allocOrderScore = $scoreMoney['useScore'];//可用积分
		$isLastOrder = false;                      //用来判断是否到最后一个订单
		$totalShop = count($carts['carts']);
		$shopNum = 0;
		foreach ($carts['carts'] as $ckey =>$shopOrder){
			$orderScoreMap[$shopOrder['shopId']]['useMoney'] = 0;
			$orderScoreMap[$shopOrder['shopId']]['useScore'] = 0;
			$shopNum++;
            if($scoreMoney['useMoney']>0){
				if($shopNum==$totalShop){
					$allocMoney = $allocOrderMoney;
					$allocScore = $allocOrderScore;
				}else{
					$allocMoney = $this->allocOrderMoney($scoreMoney['useMoney'],$carts['goodsTotalMoney'],$shopOrder['goodsMoney']);
					$allocTmpMoney = $allocOrderMoney - $allocMoney;
					//有可能计算出来金额比实际上还要大，所以要修正一下.
					if($allocTmpMoney<0){
						$allocMoney = $allocOrderMoney;
					}else{
						$allocOrderMoney = $allocTmpMoney;
					}

					$allocScore = WSTScoreToMoney($allocMoney,true);
                    $allocTmpScore = $allocOrderScore - $allocScore;
					//有可能计算出来金额比实际上还要大，修正分数
					if($allocTmpScore<0){
					    $allocScore = $allocOrderScore;
					}else{
					    $allocOrderScore = $allocTmpScore;
					}
				}
				$orderScoreMap[$shopOrder['shopId']]['useMoney'] = $allocMoney;	
				$orderScoreMap[$shopOrder['shopId']]['useScore'] = $allocScore;
			}
		}
		return $orderScoreMap;
	}

	/**
	 * 分配金额和积分
	 */
	public function allocOrderMoney($useMoney,$totalOrderMoney,$orderMoney){
		 if($useMoney>$totalOrderMoney)$useMoney = $totalOrderMoney;
         return round(($useMoney*$orderMoney)/$totalOrderMoney,2);
	}

	/**
	 * 计算可用积分和抵扣金额
	 */
	public function getOrderScoreMoney($isUseScore, $useScore, $uId=0){
        $userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
        if((int)WSTConf('CONF.isOpenScorePay')==1 && $isUseScore){
            $uses = model('common/users')->getFieldsById($userId,'userScore');
            //如果又要积分支付又传个0或者负数就默认为0...
            if($useScore<=0)$useScore = 0;
            if($uses['userScore']<$useScore)$useScore = $uses['userScore'];
            $money = WSTScoreToMoney($useScore);
            return ['useScore'=>$useScore,'useMoney'=>$money];
        }
        return ['useScore'=>0,'useMoney'=>0];
	}
	
	/**
	 * 根据订单唯一流水获取订单信息
	 */
	public function getByUnique($uId=0){
		$orderNo = input('orderNo');
		$isBatch = (int)input('isBatch/d',1);
		$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
		if($isBatch==1){
			$rs = $this->where(['userId'=>$userId,'orderunique'=>$orderNo])->field('orderId,orderNo,payType,needPay,orderunique,deliverMoney,userName,userPhone,userAddress')->select();
		}else{
			$rs = $this->where(['userId'=>$userId,'orderNo'=>$orderNo])->field('orderId,orderNo,payType,needPay,orderunique,deliverMoney,userName,userPhone,userAddress')->select();
		}
		
		$data = [];
		$data['orderunique'] = $orderNo;
		$data['list'] = [];
		$payType = 0;
		$totalMoney = 0;
		$orderIds = [0];
		foreach ($rs as $key =>$v){
			if($v['payType']==1)$payType = 1;
			$totalMoney = $totalMoney + $v['needPay'];
			$orderIds[] = $v['orderId'];
			$data['list'][] = $v;
		}
		$data['totalMoney'] = $totalMoney;
		$data['payType'] = $payType;
		//获取商品信息
		$goods = Db::name('order_goods')->where([['orderId','in',$orderIds]])->select();
		foreach ($goods as $key =>$v){
			if($v['goodsSpecNames']!=''){
				$v['goodsSpecNames'] = explode('@@_@@',$v['goodsSpecNames']);
			}else{
				$v['goodsSpecNames'] = [];
			}
			$data['goods'][$v['orderId']][] = $v;
		}
		//如果是在线支付的话就要加载支付信息
		if($data['payType']==1){
			//获取支付信息
			$payments = model('payments')->where(['isOnline'=>1,'enabled'=>1])->order('payOrder asc')->select();
			$data['payments'] = $payments;
		}
		return $data;
	}
	
	/**
	 * 获取用户订单列表
	 */
	public function userOrdersByPage($orderStatus, $isAppraise = -1, $uId=0){
		$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
		$orderNo = input('post.orderNo');
		$shopName = input('post.shopName');
		$isRefund = (int)input('post.isRefund',-1);
		$where = ['o.userId'=>$userId,'o.dataFlag'=>1];
		if(is_array($orderStatus)){
			$where[] = ['orderStatus','in',$orderStatus];
		}else{
			$where['orderStatus'] = $orderStatus;
		}
		if($isAppraise!=-1)$where['isAppraise'] = $isAppraise;
		if($orderNo!=''){
			$where[] = ['o.orderNo','like',"%$orderNo%"];
		}
		if($shopName != ''){
			$where[] = ['s.shopName','like',"%$shopName%"];
		}
		if(in_array($isRefund,[0,1])){
			$where['isRefund'] = $isRefund;
		}

		$page = $this->alias('o')->join('__SHOPS__ s','o.shopId=s.shopId','left')
		             ->join('__ORDER_COMPLAINS__ oc','oc.orderId=o.orderId','left')
		             ->join('__ORDER_REFUNDS__ orf','orf.orderId=o.orderId and orf.refundStatus!=-1','left')
		             ->where($where)
		             ->field('o.expressId,o.expressNo,o.orderRemarks,o.noticeDeliver,o.orderId,o.orderNo,s.shopName,s.shopId,s.shopQQ,s.shopWangWang,o.goodsMoney,o.totalMoney,o.realTotalMoney,
		              o.orderStatus,o.deliverType,deliverMoney,isPay,payType,payFrom,o.orderStatus,needPay,isAppraise,isRefund,orderSrc,o.createTime,o.useScore,oc.complainId,orf.id refundId,o.orderCode')
			         ->order('o.createTime', 'desc')
			         ->paginate(input('pagesize/d'))->toArray();
	    if(count($page['data'])>0){
	    	 $orderIds = [];
	    	 foreach ($page['data'] as $v){
	    	 	 $orderIds[] = $v['orderId'];
	    	 }
	    	 $goods = Db::name('order_goods')->where([['orderId','in',$orderIds]])->select();
	    	 $goodsMap = [];
	    	 foreach ($goods as $v){
	    	 	 $v['goodsSpecNames'] = str_replace('@@_@@','、',$v['goodsSpecNames']);
	    	 	 $goodsMap[$v['orderId']][] = $v;
	    	 }
	    	 foreach ($page['data'] as $key => $v){
	    	 	 $page['data'][$key]['allowRefund'] = 0;
	    	 	 //只要是已支付的，并且没有退款的，都可以申请退款操作
	    	 	 if($v['payType']==1 && $v['isRefund']==0 && $v['refundId']=='' && ($v['isPay'] ==1 || $v['useScore']>0)){
                      $page['data'][$key]['allowRefund'] = 1;
	    	 	 }
	    	 	 //货到付款中使用了积分支付的也可以申请退款
	    	 	 if($v['payType']==0 && $v['useScore']>0 && $v['refundId']=='' && $v['isRefund']==0){
                      $page['data'][$key]['allowRefund'] = 1;
	    	 	 }
	    	 	 $page['data'][$key]['list'] = $goodsMap[$v['orderId']];
	    	 	 $page['data'][$key]['isComplain'] = 1;
	    	 	 if(($v['complainId']=='') && ($v['payType']==0 || ($v['payType']==1 && $v['orderStatus']!=-2))){
	    	 	 	$page['data'][$key]['isComplain'] = '';
	    	 	 }
	    	 	 $page['data'][$key]['payTypeName'] = WSTLangPayType($v['payType']);
	    	 	 $page['data'][$key]['deliverType'] = WSTLangDeliverType($v['deliverType']==1);
	    	 	 $page['data'][$key]['status'] = WSTLangOrderStatus($v['orderStatus']);
	    		 $page['data'][$key]['orderCodeTitle'] = WSTOrderModule($v['orderCode']);
	    	 	 
	    	 }
	    	 hook('afterQueryUserOrders',['page'=>&$page]);
	    }
	    return $page;
	}
	
	/**
	 * 获取商家订单
	 */
	public function shopOrdersByPage($orderStatus, $sId=0){
		$orderNo = input('post.orderNo');
		$userName = input('post.userName');
		$userPhone = input('post.userPhone');
		$userAddress = input('post.userAddress');
		$shopName = input('post.shopName');
		$nickname = input('post.nickname');
		$goodsName = input('post.goodsName');
		$payType = (int)input('post.payType');
		$deliverType = (int)input('post.deliverType');

		$shopId = ($sId==0)?(int)session('WST_USER.shopId'):$sId;


		$where = ['o.shopId'=>$shopId,'o.dataFlag'=>1];

		if(is_array($orderStatus)){
			$where[] = ['orderStatus','in',$orderStatus];
		}else{
			$where['orderStatus'] = $orderStatus;
		}
		$where[] = ['afterSaleStatus', 'in', [0,3,5]];
		if($orderNo!=''){
			$where[] = ['orderNo','like',"%$orderNo%"];
		}
        if($userPhone!=''){
		    $where['userPhone'] = $userPhone;
        }
        if($userName!=''){
            $where[] = ['o.userName','like',"%$userName%"];
        }
        if($nickname!=''){
            $where['nickname'] = $nickname;
        }
        if($userAddress!=''){
            $where[] = ['userAddress','like',"%$userAddress%"];
        }
		if($shopName!=''){
			$where[] = ['shopName','like',"%$shopName%"];
		}
        if($goodsName!=''){
            $where[] = ['goodsName','like',"%$goodsName%"];
        }
		if($payType > -1){
			$where['payType'] =  $payType;
		}
		if($deliverType > -1){
			$where['deliverType'] =  $deliverType;
		}
		$page = $this->alias('o')->where($where)
		      ->join('__ORDER_REFUNDS__ orf','orf.orderId=o.orderId and refundStatus=0','left')
		      ->join('__ORDER_GOODS__ og','og.orderId=o.orderId','left')
              ->join('__USERS__ u','u.userId=o.userId','left')
		      ->field('o.orderRemarks,o.noticeDeliver,o.orderId,orderNo,goodsMoney,totalMoney,realTotalMoney,orderStatus,deliverType,deliverMoney,isAppraise,isRefund,o.deliverType deliverTypes
		              ,payType,payFrom,userAddress,u.nickname,orderStatus,isPay,isAppraise,orderSrc,o.createTime,orf.id refundId,o.orderCode,o.userName,o.userAddress,o.userPhone')
			  ->order('o.createTime', 'desc')
			  ->paginate()->toArray();
	    if(count($page['data'])>0){
	    	 $orderIds = [];
	    	 foreach ($page['data'] as $v){
	    	 	 $orderIds[] = $v['orderId'];
	    	 }
	    	 $goods = Db::name('order_goods')->where([['orderId','in',$orderIds]])->select();
	    	 $goodsMap = [];
	    	 foreach ($goods as $v){
	    	 	 $v['goodsSpecNames'] = str_replace('@@_@@','、',$v['goodsSpecNames']);
	    	 	 $goodsMap[$v['orderId']][] = $v;
	    	 }
	    	 foreach ($page['data'] as $key => $v){
	    	 	 $page['data'][$key]['orderCodeTitle'] = WSTOrderModule($v['orderCode']);
	    	 	 $page['data'][$key]['list'] = $goodsMap[$v['orderId']];
	    	 	 $page['data'][$key]['payTypeName'] = WSTLangPayType($v['payType']);
	    	 	 $page['data'][$key]['deliverTypeName'] = WSTLangDeliverType($v['deliverType']==1);
	    	 	 $page['data'][$key]['deliverType'] = $v['deliverType'];
	    	 	 $page['data'][$key]['status'] = WSTLangOrderStatus($v['orderStatus']);
	    	 }
	    }
	    return $page;
	}

	public function refundOrdersByPage($sId=0)
    {
        $orderNo = input('post.orderNo');
        $shopName = input('post.shopName');
        $refundType = (int)input('post.refundType');
        $refundStatus = (int)input('post.refundStatus');

        $shopId = ($sId==0)?(int)session('WST_USER.shopId'):$sId;


        $where = ['o.shopId'=>$shopId];
        $where[] = ['afterSaleStatus', '<>', 0];
        if($orderNo!=''){
            $where[] = ['orderNo','like',"%$orderNo%"];
        }
        if($shopName!=''){
            $where[] = ['shopName','like',"%$shopName%"];
        }
        if($refundType > 0){
            $where['orf.type'] =  $refundType;
        }
        if($refundStatus > -2){
            $where['orf.refundStatus'] =  $refundStatus;
        }
        $page = $this->alias('o')->where($where)
            ->join('__ORDER_REFUNDS__ orf','orf.orderId=o.orderId','left')
            ->leftJoin('datas d', 'd.id=orf.refundReson')
            ->leftJoin('express e', 'e.expressId=orf.expressId')
            ->field('o.orderRemarks,o.noticeDeliver,o.orderId,orderNo,goodsMoney,totalMoney,realTotalMoney,
                          orderStatus,deliverType,deliverMoney,isAppraise,isRefund,o.deliverType deliverTypes,
                          payType,payFrom,userAddress,orderStatus,isPay,isAppraise,userName,orderSrc,o.createTime,
                          orf.id refundId,o.orderCode,orf.refundStatus,orf.type,orf.goodsStatus,orf.backMoney,
                          orf.refundRemark,e.expressName,orf.expressId,orf.expressNo,d.dataName')
            ->order('o.createTime', 'desc')
            ->paginate()->toArray();
        if(count($page['data'])>0){
            $orderIds = [];
            foreach ($page['data'] as $v){
                $orderIds[] = $v['orderId'];
            }
            $goods = Db::name('order_goods')->where([['orderId','in',$orderIds]])->select();
            $goodsMap = [];
            foreach ($goods as $v){
                $v['goodsSpecNames'] = str_replace('@@_@@','、',$v['goodsSpecNames']);
                $goodsMap[$v['orderId']][] = $v;
            }
            foreach ($page['data'] as $key => $v){
                $page['data'][$key]['orderCodeTitle'] = WSTOrderModule($v['orderCode']);
                $page['data'][$key]['list'] = $goodsMap[$v['orderId']];
                $page['data'][$key]['payTypeName'] = WSTLangPayType($v['payType']);
                $page['data'][$key]['deliverTypeName'] = WSTLangDeliverType($v['deliverType']==1);
                $page['data'][$key]['deliverType'] = $v['deliverType'];
                $page['data'][$key]['status'] = WSTLangOrderStatus($v['orderStatus']);
                $page['data'][$key]['goodsStatus'] = $v['goodsStatus'] == 1 ? '已收货' : '未收货';
                $page['data'][$key]['refundOtherReson'] = $v['refundRemark']!=='' ? $v['refundRemark'] : '用户未填';
                $page['data'][$key]['expressName'] = $v['expressId'] == -1 ? '其它' : $v['expressName'];
            }
        }
        return $page;
    }
	/**
	 * 商家发货
	 */
	public function deliver($uId=0, $sId=0){
		$orderId = (int)input('post.id');
		$expressId = (int)input('post.expressId');
		$expressNo = input('post.expressNo');
		$shopId = ($sId==0)?(int)session('WST_USER.shopId'):$sId;
		$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
		$order = $this->where(['shopId'=>$shopId,'orderId'=>$orderId,'orderStatus'=>0])->field('orderId,orderNo,userId')->find();
		if(!empty($order)){
			Db::startTrans();
		    try{
				$data = ['orderStatus'=>1,'expressId'=>$expressId,'expressNo'=>$expressNo,'deliveryTime'=>date('Y-m-d H:i:s')];
			    $result = $this->where('orderId',$order['orderId'])->update($data);
				if(false != $result){
					//新增订单日志
					$logOrder = [];
					$logOrder['orderId'] = $orderId;
					$logOrder['orderStatus'] = 1;
					$logOrder['logContent'] = "商家已发货".(($expressNo!='')?"，快递号为：".$expressNo:"");
					$logOrder['logUserId'] = $userId;
					$logOrder['logType'] = 0;
					$logOrder['logTime'] = date('Y-m-d H:i:s');
					Db::name('log_orders')->insert($logOrder);
					//发送一条用户信息
					$tpl = WSTMsgTemplates('ORDER_DELIVERY');
	                if( $tpl['tplContent']!='' && $tpl['status']=='1'){
	                    $find = ['${ORDER_NO}','${EXPRESS_NO}'];
	                    $replace = [$order['orderNo'],($expressNo=='')?'无':$expressNo];
	                    WSTSendMsg($order['userId'],str_replace($find,$replace,$tpl['tplContent']),['from'=>1,'dataId'=>$orderId]);
	                }
	                //微信消息
		            if((int)WSTConf('CONF.wxenabled')==1){
		            	$params = [];
		            	if($expressId>0){
		            		$express = model('express')->get($expressId);
		            		$params['EXPRESS'] = $express->expressName;          
		                    $params['EXPRESS_NO'] = $expressNo;       
		            	}else{
		            		$params['EXPRESS'] = '无';
		            		$params['EXPRESS_NO'] = '无';
		            	}
		                $params['ORDER_NO'] = $order['orderNo'];  
		                
	                    WSTWxMessage(['CODE'=>'WX_ORDER_DELIVERY','userId'=>$order['userId'],'URL'=>Url('wechat/orders/index',['type'=>'waitReceive'],true,true),'params'=>$params]);
		            }
                    $this->deliverNotify($orderId);
                    (new News())->deliverGoods($orderId);
					Db::commit();
					return WSTReturn('操作成功',1);
				}
			}catch (\Exception $e) {
	            Db::rollback();
	            return WSTReturn('操作失败',-1);
	        }
		}
		return WSTReturn('操作失败，请检查订单状态是否已改变');
	}

	public function batchDeliver($fileInfo)
    {
        include_once(\Env::get('root_path') . 'extend/phpexcel/PHPExcel.php');
        if ($fileInfo['file']['type']=='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
            $msg = '文件类型错误，请重新上传';
            return json_encode(['status'=>-1,'msg'=>$msg]);
        }
        $file = $fileInfo['file']['tmp_name'];
        $objReader = \PHPExcel_IOFactory::load($file);
        $sheet = $objReader->setActiveSheetIndex(0);
        $highestRow = $sheet->getHighestRow();
        $arrColumn = ['A','B','C'];
        $data = [];
        for ($i = 1; $i <= $highestRow; $i++) {
            foreach ($arrColumn as $k=>$v) {
                $msg = $objReader->getActiveSheet()->getCell($v.$i)->getValue();
                $data[$i][] = $msg;
            }
        }
        $express = Db::name('express')->where('dataFlag',1)->field('expressId,expressName')->select();
        $expressName = array_column($express,'expressId','expressName');
        $orderNoAll = array_column($data,0);
        $orderInfoAll = Db::name('orders')->whereIn('orderNo',$orderNoAll)->field('orderNo,orderStatus')->select();
        $orderNoColumn = array_column($orderInfoAll,'orderStatus','orderNo');
        $stat = 1;
        $msg = '';

        foreach ($data as $key=>$value) {
            if (!array_key_exists($value[1],$expressName)){
                $stat = -1;
                $msg.= $value[0]."物流公司不存在 \n";
               continue;
            }
            if (!array_key_exists($value[0],$orderNoColumn) || empty($value[0])){
                $stat = -1;
                $msg.= $value[0]."订单号不存在 \n";
                continue;
            }
            $orderStatus = $orderNoColumn[$value[0]];
            //print_r($orderStatus);die;
            if ($orderStatus!=0) {
                $stat = -1;
                $msg.= $value[0]."订单号不是待发货 \n";
                continue;
            }
            $this->tmpDeliverGoods($value[0],$expressName[$value[1]], $value[2]);
        }
        return json_encode(['status'=>$stat,'msg'=>$msg]);
    }

    public function tmpDeliverGoods($orderNo,$expressId, $expressNo)
    {
            Db::startTrans();
            try{
                $data = ['orderStatus'=>1,'expressId'=>$expressId,'expressNo'=>$expressNo,'deliveryTime'=>date('Y-m-d H:i:s')];
                $result = $this->where('orderNo',$orderNo)->update($data);
                if(false != $result){
                    $order = Db::name('orders')->where(['orderNo'=>$orderNo])->field('orderId,userId')->find();
                    //新增订单日志
                    $logOrder = [];
                    $logOrder['orderId'] = $order['orderId'];
                    $logOrder['orderStatus'] = 1;
                    $logOrder['logContent'] = "商家已发货".(($expressNo!='')?"，快递号为：".$expressNo:"");
                    $logOrder['logUserId'] = $order['userId'];
                    $logOrder['logType'] = 0;
                    $logOrder['logTime'] = date('Y-m-d H:i:s');
                    Db::name('log_orders')->insert($logOrder);
                    //发送一条用户信息
//                    $tpl = WSTMsgTemplates('ORDER_DELIVERY');

                    $this->deliverNotify($order['orderId']);
                    (new News())->deliverGoods($order['orderId']);
                    Db::commit();
                    //return WSTReturn('操作成功',1);
                }
            }catch (\Exception $e) {
                Db::rollback();
                //return WSTReturn('操作失败',-1);
            }
    }

	protected function deliverNotify($orderId)
    {
        $order = $this->getOrderById($orderId);
        if ($order->payFrom === 'weixinpays') {
            $weixinPayOrder = (new WPO())->getWxPayOrderModel(['type'=>'orderPay','targetId'=>$order->orderId,'transaction_id'=>$order['tradeNo']]);
            if ($weixinPayOrder->wechatType === 'miniPrograms') {
                $wtn = new WTN();
                $wtn->deliverNotify($order, $weixinPayOrder);
            }
        }
    }
	/**
	 * 用户收货[同时给外部虚拟商品收货调用]
	 */
	public function receive($orderId = 0,$userId = 0){
		if($orderId==0 && $userId==0){
            $orderId = (int)input('post.id');
		    $userId = (int)session('WST_USER.userId');
		}
		$order = $this->alias('o')->join('__SHOPS__ s','o.shopId=s.shopId','left')
		              ->where(['o.userId'=>$userId,'o.orderId'=>$orderId,'o.orderStatus'=>1])
		              ->field('o.orderId,o.orderNo,o.payType,s.userId,s.shopId,o.orderScore,o.realTotalMoney,commissionFee')->find();
		if(!empty($order)){
			Db::startTrans();
		    try{
				$data = ['orderStatus'=>2,'receiveTime'=>date('Y-m-d H:i:s')];
			    $result = $this->where('orderId',$order['orderId'])->update($data);
				if(false != $result){
					//确认收货后执行钩子
					hook('afterUserReceive',['orderId'=>$orderId]);
					
					if(WSTConf('CONF.statementType')==1){
                        //修改商家未计算订单数
						$prefix = config('database.prefix');
						$upSql = 'update '.$prefix.'shops set noSettledOrderNum=noSettledOrderNum+1,noSettledOrderFee=noSettledOrderFee-'.$order['commissionFee'].' where shopId='.$order['shopId'];
						Db::execute($upSql);
					}else{
						//即时结算
						model('common/Settlements')->speedySettlement($orderId);
					}
					
					//新增订单日志
					$logOrder = [];
					$logOrder['orderId'] = $orderId;
					$logOrder['orderStatus'] = 2;
					$logOrder['logContent'] = "用户已收货";
					$logOrder['logUserId'] = $userId;
					$logOrder['logType'] = 0;
					$logOrder['logTime'] = date('Y-m-d H:i:s');
					Db::name('log_orders')->insert($logOrder);
					//发送一条商家信息
					$tpl = WSTMsgTemplates('ORDER_RECEIVE');
	                if( $tpl['tplContent']!='' && $tpl['status']=='1'){
	                    $find = ['${ORDER_NO}'];
	                    $replace = [$order['orderNo']];
	                    
	                	$msg = array();
			            $msg["shopId"] = $order["shopId"];
			            $msg["tplCode"] = $tpl["tplCode"];
			            $msg["msgType"] = 1;
			            $msg["content"] = str_replace($find,$replace,$tpl['tplContent']);
			            $msg["msgJson"] = ['from'=>1,'dataId'=>$orderId];
			            model("common/MessageQueues")->add($msg);
	                }
					
					//给用户增加积分
					if(WSTConf("CONF.isOrderScore")==1 && $order['orderScore']>0){
						$score = [];
						$score['userId'] = $userId;
						$score['score'] = $order['orderScore'];
						$score['dataSrc'] = 1;
						$score['dataId'] = $orderId;
						$score['dataRemarks'] = "交易订单【".$order['orderNo']."】获得积分".$order['orderScore']."个";
						$score['scoreType'] = 1;
						model('UserScores')->add($score);
					}
					//微信消息
		            if((int)WSTConf('CONF.wxenabled')==1){
		            	$params = [];
		                $params['ORDER_NO'] = $order['orderNo'];  
		                $params['ORDER_TIME'] = date('Y-m-d H:i:s');
	                    //WSTWxMessage(['CODE'=>'WX_ORDER_RECEIVE','userId'=>$order['userId'],'URL'=>Url('wechat/orders/sellerorder','',true,true),'params'=>$params]);
		            	$msg = array();
						$tplCode = "WX_ORDER_RECEIVE";
						$msg["shopId"] = $order["shopId"];
			            $msg["tplCode"] = $tplCode;
			            $msg["msgType"] = 4;
			            $msg["paramJson"] = ['CODE'=>$tplCode,'URL'=>Url('wechat/orders/sellerorder','',true,true),'params'=>$params] ;
			            $msg["msgJson"] = "";
			            model("common/MessageQueues")->add($msg);
		            } 
					Db::commit();
					return WSTReturn('操作成功',1);
				}
		    }catch (\Exception $e) {
	            Db::rollback();
	            return WSTReturn('操作失败',-1);
	        }
		}
		return WSTReturn('操作失败，请检查订单状态是否已改变');
	}
	/**
	 * 用户取消订单
	 */
	public function cancel($uId=0){
		$orderId = (int)input('post.id');
		hook('beforeCancelOrder',['orderId'=>$orderId]);
		$reason = (int)input('post.reason');
		$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
		$order = $this->alias('o')->join('__SHOPS__ s','o.shopId=s.shopId','left')
		              ->where([['o.orderStatus','in',[-2,0]],['o.userId','=',$userId],['o.orderId','=',$orderId]])
		              
		              ->field('o.orderId,o.orderNo,s.userId,s.shopId,o.orderCode,o.isPay,o.orderType,o.payType,o.orderStatus,o.useScore,o.scoreMoney,o.realTotalMoney')->find();
		$reasonData = WSTDatas('ORDER_CANCEL',$reason);
		if(empty($reasonData))return WSTReturn("无效的取消原因");
		if(!empty($order)){
			Db::startTrans();
		    try{
				$data = ['orderStatus'=>-1,'cancelReason'=>$reason];
				//把实付金额设置为0
				if($order['payType']==0 || ($order['payType']==1 && $order['isPay']==0))$data['realTotalMoney'] = 0;
			    $result = $this->where('orderId',$order['orderId'])->update($data);
				if(false != $result){
                    //正常订单商品库存处理
                    $goods = Db::name('order_goods')->alias('og')->join('__GOODS__ g','og.goodsId=g.goodsId','inner')
						           ->where('orderId',$orderId)->field('og.*,g.isSpec')->select();
                    //返还商品库存
					foreach ($goods as $key => $v){
						//处理虚拟产品
						if($v['goodsType']==1){
	                        $extraJson = json_decode($v['extraJson'],true);
	                        foreach ($extraJson as  $ecard) {
	                            Db::name('goods_virtuals')->where('id',$ecard['cardId'])->update(['orderId'=>0,'orderNo'=>'','isUse'=>0]);
	                        }
	                        $counts = Db::name('goods_virtuals')->where(['dataFlag'=>1,'goodsId'=>$v['goodsId'],'isUse'=>0])->count();
	                        Db::name('goods')->where('goodsId',$v['goodsId'])->setField('goodsStock',$counts);
						}else{
							if($order['orderCode']=='order'){
								//修改库存
								if($v['isSpec']>0){
							        Db::name('goods_specs')->where('id',$v['goodsSpecId'])->setInc('specStock',$v['goodsNum']);
								}
								Db::name('goods')->where('goodsId',$v['goodsId'])->setInc('goodsStock',$v['goodsNum']);
							}
						}
                    }
					
					//新增订单日志
					$logOrder = [];
					$logOrder['orderId'] = $orderId;
					$logOrder['orderStatus'] = -1;
					$logOrder['logContent'] = "用户取消订单，取消原因：".$reasonData['dataName'];
					$logOrder['logUserId'] = $userId;
					$logOrder['logType'] = 0;
					$logOrder['logTime'] = date('Y-m-d H:i:s');
					Db::name('log_orders')->insert($logOrder);
					//提交订单后执行钩子
					hook('afterCancelOrder',['orderId'=>$orderId]);
					//发送一条商家信息
					$tpl = WSTMsgTemplates('ORDER_CANCEL');
	                if( $tpl['tplContent']!='' && $tpl['status']=='1'){
	                    $find = ['${ORDER_NO}','${REASON}'];
	                    $replace = [$order['orderNo'],$reasonData['dataName']];
	                   
	                	$msg = array();
			            $msg["shopId"] = $order["shopId"];
			            $msg["tplCode"] = $tpl["tplCode"];
			            $msg["msgType"] = 1;
			            $msg["content"] = str_replace($find,$replace,$tpl['tplContent']);
			            $msg["msgJson"] = ['from'=>1,'dataId'=>$orderId];
			            model("common/MessageQueues")->add($msg);
	                }
	                //判断是否需要发送管理员短信
					$tpl = WSTMsgTemplates('PHONE_ADMIN_CANCEL_ORDER');
					if((int)WSTConf('CONF.smsOpen')==1 && (int)WSTConf('CONF.smsCancelOrderTip')==1 &&  $tpl['tplContent']!='' && $tpl['status']=='1'){
						$params = ['tpl'=>$tpl,'params'=>['ORDER_NO'=>$order['orderNo']]];
						$staffs = Db::name('staffs')->where([['staffId','in',explode(',',WSTConf('CONF.cancelOrderTipUsers'))],['staffStatus','=',1],['dataFlag','=',1]])->field('staffPhone')->select();
						for($i=0;$i<count($staffs);$i++){
							if($staffs[$i]['staffPhone']=='')continue;
							$m = new LogSms();
							$rv = $m->sendAdminSMS(0,$staffs[$i]['staffPhone'],$params,'cancel','');
						}
					}
	                //微信消息
		            if((int)WSTConf('CONF.wxenabled')==1){
		            	$params = [];
		                $params['ORDER_NO'] = $order['orderNo'];            
		                $goodsNames = [];
		                foreach ($goods as $gkey =>$g){
	                        $goodsNames[] = $g['goodsName']."*".$g['goodsNum'];
		                }
		                $params['REASON'] = $reasonData['dataName'];
		                $params['GOODS'] = implode(',',$goodsNames);
		                $params['MONEY'] = $order['realTotalMoney'] + $order['scoreMoney'];
	                    //WSTWxMessage(['CODE'=>'WX_ORDER_CANCEL','userId'=>$order['userId'],'URL'=>Url('wechat/orders/sellerorder','',true,true),'params'=>$params]);
		            	$msg = array();
						$tplCode = "WX_ORDER_CANCEL";
						$msg["shopId"] = $order["shopId"];
			            $msg["tplCode"] = $tplCode;
			            $msg["msgType"] = 4;
			            $msg["paramJson"] = ['CODE'=>'WX_ORDER_CANCEL','URL'=>Url('wechat/orders/sellerorder','',true,true),'params'=>$params];
			            $msg["msgJson"] = "";
			            model("common/MessageQueues")->add($msg);
		                //判断是否需要发送给管理员消息
		                if((int)WSTConf('CONF.wxCancelOrderTip')==1){
		                	$params = [];
			                $params['ORDER_NO'] = $order['orderNo'];            
			                $goodsNames = [];
			                foreach ($goods as $gkey =>$g){
		                        $goodsNames[] = $g['goodsName']."*".$g['goodsNum'];
			                }
			                $params['REASON'] = $reasonData['dataName'];
			                $params['GOODS'] = implode(',',$goodsNames);
			                $params['MONEY'] = $order['realTotalMoney'] + $order['scoreMoney'];
			            	WSTWxBatchMessage(['CODE'=>'WX_ADMIN_ORDER_CANCEL','userType'=>3,'userId'=>explode(',',WSTConf('CONF.cancelOrderTipUsers')),'params'=>$params]);
		                }
		            } 
					Db::commit();
					return WSTReturn('订单取消成功',1);
				}
			}catch (\Exception $e) {
				echo $e;
		        Db::rollback();
	            return WSTReturn('操作失败',-1);
	        }
		}
		return WSTReturn('操作失败，请检查订单状态是否已改变');
	}
	/**
	 * 用户拒收订单
	 */
	public function reject($uId=0){
		$orderId = (int)input('post.id');
		$reason = (int)input('post.reason');
		$content = input('post.content');
		$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
		$order = $this->alias('o')->join('__SHOPS__ s','o.shopId=s.shopId','left')
		              ->where(['o.userId'=>$userId,'o.orderId'=>$orderId,'o.orderStatus'=>1])
		              ->field('o.orderId,o.orderNo,o.shopId,s.userId,payType,o.userAddress,o.userName,o.realTotalMoney,o.scoreMoney')->find();
		$reasonData = WSTDatas('ORDER_REJECT',$reason);
		if(empty($reasonData))return WSTReturn("无效的拒收原因");
		if($reason==10000 && $content=='')return WSTReturn("请输入拒收原因");
		if(!empty($order)){
			Db::startTrans();
		    try{
				$data = ['orderStatus'=>-3,'rejectReason'=>$reason];
				if($reason==10000)$data['rejectOtherReason'] = $content;
				//如果是货到付款拒收的话，把实付金额设置为0
				if($order['payType']==0)$data['realTotalMoney'] = 0;
			    $result = $this->where('orderId',$order['orderId'])->update($data);
				if(false != $result){
					//新增订单日志
					$logOrder = [];
					$logOrder['orderId'] = $orderId;
					$logOrder['orderStatus'] = -3;
					$logOrder['logContent'] = "用户拒收订单，拒收原因：".$reasonData['dataName'].(($reason==10000)?"-".$content:"");
					$logOrder['logUserId'] = $userId;
					$logOrder['logType'] = 0;
					$logOrder['logTime'] = date('Y-m-d H:i:s');
					Db::name('log_orders')->insert($logOrder);
					//发送一条商家信息
					$tpl = WSTMsgTemplates('ORDER_REJECT');
	                if( $tpl['tplContent']!='' && $tpl['status']=='1'){
	                    $find = ['${ORDER_NO}','${REASON}'];
	                    $replace = [$order['orderNo'],$reasonData['dataName'].(($reason==10000)?"-".$content:"")];
	                   
	                	$msg = array();
			            $msg["shopId"] = $order['shopId'];
			            $msg["tplCode"] = $tpl["tplCode"];
			            $msg["msgType"] = 1;
			            $msg["content"] = str_replace($find,$replace,$tpl['tplContent']);
			            $msg["msgJson"] = ['from'=>1,'dataId'=>$orderId];
			            model("common/MessageQueues")->add($msg);
	                }
	                //判断是否需要发送管理员短信
					$tpl = WSTMsgTemplates('PHONE_ADMIN_REJECT_ORDER');
					if((int)WSTConf('CONF.smsOpen')==1 && (int)WSTConf('CONF.smsRejectOrderTip')==1 &&  $tpl['tplContent']!='' && $tpl['status']=='1'){
						$params = ['tpl'=>$tpl,'params'=>['ORDER_NO'=>$order['orderNo']]];
						$staffs = Db::name('staffs')->where([['staffId','in',explode(',',WSTConf('CONF.rejectOrderTipUsers'))],['staffStatus','=',1],['dataFlag','=',1]])->field('staffPhone')->select();
						for($i=0;$i<count($staffs);$i++){
							if($staffs[$i]['staffPhone']=='')continue;
							$m = new LogSms();
							$rv = $m->sendAdminSMS(0,$staffs[$i]['staffPhone'],$params,'cancel','');
						}
					}
					//微信消息
		            if((int)WSTConf('CONF.wxenabled')==1){
		            	$params = [];
		                $params['ORDER_NO'] = $order['orderNo'];  
	                    $goods = Db::name('order_goods')->where('orderId',$order['orderId'])->select();           
		                $goodsNames = [];
		                foreach ($goods as $gkey =>$goods){
	                        $goodsNames[] = $goods['goodsName']."*".$goods['goodsNum'];
		                }
		                $params['GOODS'] = implode(',',$goodsNames);
		                $params['MONEY'] = $order['realTotalMoney'] + $order['scoreMoney'];
		                $params['ADDRESS'] = $order['userAddress']." ".$order['userName'];
		                $params['REASON'] = $reasonData['dataName'].(($reason==10000)?"-".$content:"");
	                    
		            	$msg = array();
						$tplCode = "WX_ORDER_REJECT";
						$msg["shopId"] = $order['shopId'];
			            $msg["tplCode"] = $tplCode;
			            $msg["msgType"] = 4;
			            $msg["paramJson"] = ['CODE'=>$tplCode,'URL'=>Url('wechat/orders/sellerorder','',true,true),'params'=>$params];
			            $msg["msgJson"] = "";
			            model("common/MessageQueues")->add($msg);

		                //判断是否需要发送给管理员消息
		                if((int)WSTConf('CONF.wxRejectOrderTip')==1){
		                	$params = [];
			                $params['ORDER_NO'] = $order['orderNo'];  
		                    $goods = Db::name('order_goods')->where('orderId',$order['orderId'])->select();           
			                $goodsNames = [];
			                foreach ($goods as $gkey =>$goods){
		                        $goodsNames[] = $goods['goodsName']."*".$goods['goodsNum'];
			                }
			                $params['GOODS'] = implode(',',$goodsNames);
			                $params['MONEY'] = $order['realTotalMoney'] + $order['scoreMoney'];
			                $params['ADDRESS'] = $order['userAddress']." ".$order['userName'];
			                $params['REASON'] = $reasonData['dataName'].(($reason==10000)?"-".$content:"");
			            	WSTWxBatchMessage(['CODE'=>'WX_ADMIN_ORDER_REJECT','userType'=>3,'userId'=>explode(',',WSTConf('CONF.rejectOrderTipUsers')),'params'=>$params]);
		                }
		            } 
					Db::commit();
					return WSTReturn('操作成功',1);
				}
			}catch (\Exception $e) {
		        Db::rollback();
	            return WSTReturn('操作失败',-1);
	        }
		}
		return WSTReturn('操作失败，请检查订单状态是否已改变');
	}
	/**
	 * 获取订单价格
	 */
	public function getMoneyByOrder($orderId = 0){
		$orderId = ($orderId>0)?$orderId:(int)input('post.id');
		return $this->where('orderId',$orderId)->field('orderId,orderNo,goodsMoney,deliverMoney,useScore,scoreMoney,totalMoney,realTotalMoney')->find();
	}
	

	/**
	 * 修改订单价格
	 */
	public function editOrderMoney($uId=0, $sId=0){
		$orderId = input('post.id');
		$orderMoney = (float)input('post.orderMoney');
		$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
		$shopId = ($sId==0)?(int)session('WST_USER.shopId'):$sId;
		if($orderMoney<0.01)return WSTReturn("订单价格不能小于0.01");
		Db::startTrans();
		try{

			$data = array();
			$data["realTotalMoney"] = $orderMoney;
			$data["needPay"] = $orderMoney;
			$data["payRand"] = array("exp","payRand+1");
			$result = $this->where(['orderId'=>$orderId,'shopId'=>$shopId,'orderStatus'=>-2])->update($data);

			if(false !== $result){
				//新增订单日志
				$logOrder = [];
				$logOrder['orderId'] = $orderId;
				$logOrder['orderStatus'] = -2;
				$logOrder['logContent'] = "商家修改订单价格为：".$orderMoney;
				$logOrder['logUserId'] = $userId;
				$logOrder['logType'] = 0;
				$logOrder['logTime'] = date('Y-m-d H:i:s');
				Db::name('log_orders')->insert($logOrder);
				Db::commit();
				return WSTReturn('操作成功',1);
			}
		}catch (\Exception $e) {
		    Db::rollback();
	        return WSTReturn('操作失败',-1);
	    }
	}
	
	/**
	 * 获取订单详情
	 */
	public function getByView($orderId, $uId=0){
		$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
		$shopId = ($uId==0)?(int)session('WST_USER.shopId'):$uId;
		$orders = Db::name('orders')->alias('o')->join('__EXPRESS__ e','o.expressId=e.expressId','left')
		               ->join('__SHOPS__ s','o.shopId=s.shopId','left')
		               ->join('__ORDER_REFUNDS__ orf ','o.orderId=orf.orderId','left')
		               ->where('o.dataFlag=1 and o.orderId='.$orderId.' and ( o.userId='.$userId.' or o.shopId='.$shopId.')')
		               ->field('o.*,e.expressName,s.areaId shopAreaId,s.shopAddress,s.shopTel,s.shopName,s.shopQQ,s.shopWangWang,orf.id refundId,orf.refundRemark,orf.refundStatus,orf.refundTime,orf.backMoney,orf.backMoney')->find();
		if(empty($orders))return WSTReturn("无效的订单信息");
		// 获取店铺地址
		$orders['shopAddr'] = model('common/areas')->getParentNames($orders['shopAreaId']);
		$orders['shopAddress'] = implode('',$orders['shopAddr']).$orders['shopAddress'];
		unset($orders['shopAddr']);
		//获取订单信息
		$orders['log'] =Db::name('log_orders')->where('orderId',$orderId)->order('logId asc')->select();
		//获取订单商品
		$orders['goods'] = Db::name('order_goods')->alias('og')->join('__GOODS__ g','g.goodsId=og.goodsId','left')->where('orderId',$orderId)->field('og.*,g.goodsSn')->order('id asc')->select();
		//如果是虚拟商品
		if($orders['orderType']==1){
			foreach ($orders['goods'] as $key => $v) {
				$orders['goods'][$key]['extraJson'] = json_decode($v['extraJson'],true);
			}
		}
		return $orders;
	}



	/**
	* 根据订单id获取 商品信息跟商品评价
	*/
	public function getOrderInfoAndAppr($uId=0){
		$orderId = (int)input('oId');
		$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;

		$goodsInfo = Db::name('order_goods')
					->field('id,orderId,goodsName,goodsId,goodsSpecNames,goodsImg,goodsSpecId,goodsCode')
					->where(['orderId'=>$orderId])
					->select();
		//根据商品id 与 订单id 取评价
		$alreadys = 0;// 已评价商品数
		$count = count($goodsInfo);//订单下总商品数
		if($count>0){
			foreach($goodsInfo as $k=>$v){
				$goodsInfo[$k]['goodsSpecNames'] = str_replace('@@_@@', ';', $v['goodsSpecNames']);
				$appraise = Db::name('goods_appraises')
							->field('goodsScore,serviceScore,timeScore,content,images,createTime')
							->where(['goodsId'=>$v['goodsId'],
							         'goodsSpecId'=>$v['goodsSpecId'],
									 'orderId'=>$orderId,
									 'dataFlag'=>1,
									 'userId'=>$userId,
									 'orderGoodsId'=>$v['id'],
									 ])->find();
				if(!empty($appraise)){
					++$alreadys;
					$appraise['images'] = ($appraise['images']!='')?explode(',', $appraise['images']):[];
				}
				$goodsInfo[$k]['appraise'] = $appraise;
			}
		}
		return ['count'=>$count,'data'=>$goodsInfo,'alreadys'=>$alreadys];

	}
	
	/**
	 * 检查订单是否已支付
	 */
	public function checkOrderPay (){
		$userId = (int)session('WST_USER.userId');
		$orderNo = input("orderNo");
		$isBatch = (int)input("isBatch");
		$rs = array();
		$where = ["userId"=>$userId,"dataFlag"=>1,"orderStatus"=>-2,"isPay"=>0,"payType"=>1];
		if($isBatch==1){
			$where['orderunique'] = $orderNo;
		}else{
			$where['orderNo'] = $orderNo;
		}
		$rs = $this->field('orderId,orderNo')->where($where)->select();
		if(count($rs)>0){
			return WSTReturn('',1);
		}else{
			return WSTReturn('订单已支付',-1);
		}
	}
	
	/**
	 * 检查订单是否已支付
	 */
	public function checkOrderPay2 ($obj){
		$userId = $obj["userId"];
		$orderNo = $obj["orderNo"];
		$isBatch = $obj["isBatch"];
		$rs = array();
		$where = ["userId"=>$userId,"dataFlag"=>1,"orderStatus"=>-2,"isPay"=>0,"payType"=>1];
		if($isBatch==1){
			$where['orderunique'] = $orderNo;
		}else{
			$where['orderNo'] = $orderNo;
		}
		$rs = $this->field('orderId,orderNo')->where($where)->select();
		if(count($rs)>0){
			return WSTReturn('',1);
		}else{
			return WSTReturn('订单已支付',-1);
		}
	}
	
	
	/**
	 * 虚拟商品支付处理
	 */
	public function handleVirtualGoods($orderId){
		$order= Db::name('orders')->alias('o')->join('__SHOPS__ s','o.shopId=s.shopId ','inner')
				->where('orderId',$orderId)->field('orderId,orderNo,o.shopId,s.userId,o.userId ouserId,o.realTotalMoney,o.payFrom')
				->find();
		//新增订单日志
		$logOrder = [];
		$logOrder['orderId'] = $order['orderId'];
		$logOrder['orderStatus'] = 0;
		$logOrder['logContent'] = "商家已发货";
		$logOrder['logUserId'] = $order['userId'];
		$logOrder['logType'] = 0;
		$logOrder['logTime'] = date('Y-m-d H:i:s');
		Db::name('log_orders')->insert($logOrder);
		$logOrder = [];
		$logOrder['orderId'] = $order['orderId'];
		$logOrder['orderStatus'] = 0;
		$logOrder['logContent'] = "用户已收货";
		$logOrder['logUserId'] = $order['ouserId'];
		$logOrder['logType'] = 0;
		$logOrder['logTime'] = date('Y-m-d H:i:s');
		Db::name('log_orders')->insert($logOrder);
		//修改订单状态
		Db::name('orders')->where('orderId',$order['orderId'])->update(['orderStatus'=>2,'deliveryTime'=>date('Y-m-d H:i:s'),'receiveTime'=>date('Y-m-d H:i:s')]);
		//分配卡券号
        $orderGoods = Db::name('order_goods')->where(['orderId'=>$order['orderId'],'goodsType'=>1])->field('id,goodsName,extraJson')->find();
        $cardIds = [];
        $extraJson = json_decode($orderGoods['extraJson'],true);
        foreach ($extraJson as $ogextra) {
            $cardIds[] = $ogextra['cardId'];
        }
        $cards = model('common/GoodsVirtuals')->where([['id','in',$cardIds]])->field('id,cardNo,cardPwd')->select();
        $cardmap = [];
        foreach ($cards as $card) {
            $cardmap[$card['id']] = $card;
        }
        $ogcards = [];
        $extra = json_decode($orderGoods['extraJson'],true);
        foreach ($extra as $ogextra) {
        	$ogextra['cardId'] = $cardmap[$ogextra['cardId']]['id'];
	        $ogextra['cardNo'] = $cardmap[$ogextra['cardId']]['cardNo'];
	        $ogextra['cardPwd'] = $cardmap[$ogextra['cardId']]['cardPwd'];
	        $ogextra['isUse'] = 0;
	        $ogcards[] = $ogextra;
        }
        Db::name('order_goods')->where('id',$orderGoods['id'])->update(['extraJson'=>json_encode($ogcards)]);
        //即时结算
		model('common/Settlements')->speedySettlement($orderId);
        //发送一条商家信息
		$tpl = WSTMsgTemplates('ORDER_SHOP_AUTO_DELIVERY');
	    if( $tpl['tplContent']!='' && $tpl['status']=='1'){
	        $find = ['${ORDER_NO}','${GOODS}'];
	        $replace = [$order['orderNo'],$orderGoods['goodsName']];
	        
	    	$msg = array();
            $msg["shopId"] = $order["shopId"];
            $msg["tplCode"] = $tpl["tplCode"];
            $msg["msgType"] = 1;
            $msg["content"] = str_replace($find,$replace,$tpl['tplContent']);
            $msg["msgJson"] = ['from'=>1,'dataId'=>$order['orderId']];
            model("common/MessageQueues")->add($msg);
	    }
	    //发送一条用户信息
		$tpl = WSTMsgTemplates('ORDER_USER_AUTO_DELIVERY');
	    if( $tpl['tplContent']!='' && $tpl['status']=='1'){
	        $find = ['${ORDER_NO}','${GOODS}'];
	        $replace = [$order['orderNo'],$orderGoods['goodsName']];
	        WSTSendMsg($order["ouserId"],str_replace($find,$replace,$tpl['tplContent']),['from'=>1,'dataId'=>$order['orderId']]);
	    }
        //判断是否需要发送管理员短信
		$tpl = WSTMsgTemplates('PHONE_ADMIN_PAY_ORDER');
		if((int)WSTConf('CONF.smsOpen')==1 && (int)WSTConf('CONF.smsPayOrderTip')==1 &&  $tpl['tplContent']!='' && $tpl['status']=='1'){
			$params = ['tpl'=>$tpl,'params'=>['ORDER_NO'=>$order['orderNo']]];
			$staffs = Db::name('staffs')->where([['staffId','in',explode(',',WSTConf('CONF.payOrderTipUsers'))],['staffStatus','=',1],['dataFlag','=',1]])->field('staffPhone')->select();
			for($i=0;$i<count($staffs);$i++){
				if($staffs[$i]['staffPhone']=='')continue;
				$m = new LogSms();
				$rv = $m->sendAdminSMS(0,$staffs[$i]['staffPhone'],$params,'handleVirtualGoods','');
			}
		}
		//微信消息-已支付
		if((int)WSTConf('CONF.wxenabled')==1){
			$params = [];
			$params['ORDER_NO'] = $order['orderNo'];
			$params['PAY_TIME'] = date('Y-m-d H:i:s');             
			$params['MONEY'] = $order['realTotalMoney'];
			$params['PAY_SRC'] = WSTLangPayFrom($order['payFrom']);
			//WSTWxMessage(['CODE'=>'WX_ORDER_PAY','userId'=>$order["userId"],'URL'=>Url('wechat/orders/sellerorder','',true,true),'params'=>$params]);
			$msg = array();
			$tplCode = "WX_ORDER_PAY";
			$msg["shopId"] = $order["shopId"];
            $msg["tplCode"] = $tplCode;
            $msg["msgType"] = 4;
            $msg["paramJson"] = ['CODE'=>$tplCode,'URL'=>Url('wechat/orders/sellerorder','',true,true),'params'=>$params];
            $msg["msgJson"] = "";
            model("common/MessageQueues")->add($msg);
			//判断是否需要发送给管理员消息
			if((int)WSTConf('CONF.wxPayOrderTip')==1){
				$params = [];
			    $params['ORDER_NO'] = $order['orderNo'];
				$params['PAY_TIME'] = date('Y-m-d H:i:s');             
				$params['MONEY'] = $order['realTotalMoney'];
				$params['PAY_SRC'] = WSTLangPayFrom($order['payFrom']);
				WSTWxBatchMessage(['CODE'=>'WX_ADMIN_ORDER_PAY','userType'=>3,'userId'=>explode(',',WSTConf('CONF.payOrderTipUsers')),'params'=>$params]);
			}
		} 

	}
	

	/**
	 * 完成支付订单
	 */
	public function complatePay ($obj){
		$trade_no = $obj["trade_no"];
		$isBatch = (int)$obj["isBatch"];
		$orderNo = $obj["out_trade_no"];
		$userId = (int)$obj["userId"];
		$payFrom = $obj["payFrom"];
		$payMoney = (float)$obj["total_fee"];
		if($payFrom!=''){
			$cnt = model('orders')
			->where(['payFrom'=>$payFrom,"userId"=>$userId,"tradeNo"=>$trade_no])
			->count();
			if($cnt>0){
				return WSTReturn('订单已支付',-1);
			}
		}
		$where = ["userId"=>$userId,"dataFlag"=>1,"orderStatus"=>-2,"isPay"=>0,"payType"=>1];
		$where[] = ["needPay",">",0];
		if($isBatch==1){
			$where['orderunique'] = $orderNo;
		}else{
			$where['orderNo'] = $orderNo;
		}
		$orders = model('orders')->where($where)->field('needPay,orderId,orderType,orderNo,shopId,payFrom,realTotalMoney')->select();
	    if(count($orders)==0)return WSTReturn('无效的订单信息',-1);
		$needPay = 0;
	    foreach ($orders as $key => $v) {
	    	$needPay += $v['needPay'];
	    }
		if($needPay>$payMoney){
			return WSTReturn('支付金额不正确',-1);
		}
		Db::startTrans();
		try{
			$data = array();
			$data["needPay"] = 0;
			$data["isPay"] = 1;
			$data["orderStatus"] = 0;
			$data["tradeNo"] = $trade_no;
			$data["payFrom"] = $payFrom;
			$data["payTime"] = date("Y-m-d H:i:s");
			$data["isBatch"] = $isBatch;
			$data["totalPayFee"] = $payMoney*100;
			$rs = model('orders')->where($where)->update($data);
	
			if($needPay>0 && false != $rs){
				foreach ($orders as $key =>$v){
					$orderId = $v["orderId"];
					$shop = model('shops')->get($v->shopId);
					
					//新增订单日志
					$logOrder = [];
					$logOrder['orderId'] = $orderId;
					$logOrder['orderStatus'] = 0;
					$logOrder['logContent'] = "订单已支付,下单成功";
					$logOrder['logUserId'] = $userId;
					$logOrder['logType'] = 0;
					$logOrder['logTime'] = date('Y-m-d H:i:s');
					Db::name('log_orders')->insert($logOrder);
					//创建一条充值流水记录
					$lm = [];
					$lm['targetType'] = 0;
					$lm['targetId'] = $userId;
					$lm['dataId'] = $orderId;
					$lm['dataSrc'] = 1;
					$lm['remark'] = '交易订单【'.$v['orderNo'].'】充值¥'.$needPay;
					$lm['moneyType'] = 1;
					$lm['money'] = $needPay;
					$lm['payType'] = $payFrom;
					$lm['tradeNo'] = $trade_no;
					$lm['createTime'] = date('Y-m-d H:i:s');
					model('LogMoneys')->create($lm);
					//创建一条支出流水记录
					$lm = [];
					$lm['targetType'] = 0;
					$lm['targetId'] = $userId;
					$lm['dataId'] = $orderId;
					$lm['dataSrc'] = 1;
					$lm['remark'] = '交易订单【'.$v['orderNo'].'】支出¥'.$needPay;
					$lm['moneyType'] = 0;
					$lm['money'] = $needPay;
					$lm['payType'] = 0;
					$lm['createTime'] = date('Y-m-d H:i:s');
					model('LogMoneys')->create($lm);
					//虚拟商品处理
	                if($v['orderType']==1){
	                    	$this->handleVirtualGoods($v['orderId']);
	                }else{
						//发送一条商家信息
						$tpl = WSTMsgTemplates('ORDER_HASPAY');
				        if( $tpl['tplContent']!='' && $tpl['status']=='1'){
				            $find = ['${ORDER_NO}'];
				            $replace = [$v['orderNo']];
				            
				            $msg = array();
				            $msg["shopId"] = $shop["shopId"];
				            $msg["tplCode"] = $tpl["tplCode"];
				            $msg["msgType"] = 1;
				            $msg["content"] = str_replace($find,$replace,$tpl['tplContent']);
				            $msg["msgJson"] = ['from'=>1,'dataId'=>$orderId];
				            model("common/MessageQueues")->add($msg);
				        }

                        //判断是否需要发送管理员短信
		                $tpl = WSTMsgTemplates('PHONE_ADMIN_PAY_ORDER');
		                if((int)WSTConf('CONF.smsOpen')==1 && (int)WSTConf('CONF.smsPayOrderTip')==1 &&  $tpl['tplContent']!='' && $tpl['status']=='1'){
							$params = ['tpl'=>$tpl,'params'=>['ORDER_NO'=>$v['orderNo']]];
							$staffs = Db::name('staffs')->where([['staffId','in',explode(',',WSTConf('CONF.payOrderTipUsers'))],['staffStatus','=',1],['dataFlag','=',1]])->field('staffPhone')->select();
							for($i=0;$i<count($staffs);$i++){
								if($staffs[$i]['staffPhone']=='')continue;
								$m = new LogSms();
					            $rv = $m->sendAdminSMS(0,$staffs[$i]['staffPhone'],$params,'complatePay','');
					        }
		                }
						//微信消息
					    if((int)WSTConf('CONF.wxenabled')==1){
						    $params = [];
						    $params['ORDER_NO'] = $v['orderNo'];
					        $params['PAY_TIME'] = date('Y-m-d H:i:s');             
						    $params['MONEY'] = $v['realTotalMoney'];
						    $params['PAY_SRC'] = WSTLangPayFrom($v['payFrom']);
				           
					        $msg = array();
							$tplCode = "WX_ORDER_PAY";
							$msg["shopId"] = $shop["shopId"];
				            $msg["tplCode"] = $tplCode;
				            $msg["msgType"] = 4;
				            $msg["paramJson"] = ['CODE'=>$tplCode,'URL'=>Url('wechat/orders/sellerorder','',true,true),'params'=>$params];
				            $msg["msgJson"] = "";
				            model("common/MessageQueues")->add($msg);
					        //判断是否需要发送给管理员消息
			                if((int)WSTConf('CONF.wxPayOrderTip')==1){
			                	$params = [];
			                	$params['ORDER_NO'] = $v['orderNo'];
						        $params['PAY_TIME'] = date('Y-m-d H:i:s');             
							    $params['MONEY'] = $v['realTotalMoney'];
							    $params['PAY_SRC'] = WSTLangPayFrom($v['payFrom']);
				            	WSTWxBatchMessage(['CODE'=>'WX_ADMIN_ORDER_PAY','userType'=>3,'userId'=>explode(',',WSTConf('CONF.payOrderTipUsers')),'params'=>$params]);
			                }
					    } 
	                }
				}
			}else{
				$data = array();
				$data["userMoney"] = array("exp","userMoney+".$payMoney);
				Db::name('users')->where("userId",$userId)->update($data);
				//创建一条充值流水记录
				$lm = [];
				$lm['targetType'] = 0;
				$lm['targetId'] = $userId;
				$lm['dataId'] = $orderNo;
				$lm['dataSrc'] = 1;
				$lm['remark'] = '交易订单充值¥'.$payMoney;
				$lm['moneyType'] = 1;
				$lm['money'] = $payMoney;
				$lm['payType'] = $payFrom;
				$lm['tradeNo'] = $trade_no;
				$lm['createTime'] = date('Y-m-d H:i:s');
				model('LogMoneys')->create($lm);
			}
			Db::commit();
			return WSTReturn('支付成功',1);
		}catch (\Exception $e) {
			Db::rollback();
			return WSTReturn('操作失败',-1);
		}
	}
	
	/**
	 * 获取支付订单信息
	 */
	public function getPayOrders ($obj){
		$userId = (int)$obj["userId"];
		$orderNo = $obj["orderNo"];
		$isBatch = (int)$obj["isBatch"];
		$needPay = 0;
		$where = ["userId"=>$userId,"dataFlag"=>1,"orderStatus"=>-2,"isPay"=>0,"payType"=>1];
		$where[] = ["needPay",">",0];
		if($isBatch==1){
			$where['orderunique'] = $orderNo;
		}else{
			$where['orderNo'] = $orderNo;
		}
		$data = array();
		$needPay = model('orders')->where($where)->sum('needPay');
		$payRand = model('orders')->where($where)->max('payRand');
		$data["needPay"] = $needPay;
		$data["payRand"] = $payRand;
		return $data;
	}
	
	/**
	 * 导出订单
	 */
	public function toExport(){
		$name='order';
		$where = ['o.dataFlag'=>1];
		$orderStatus = (int)input('orderStatus',0);
		if($orderStatus==0){
			$name='PendingDelOrder';
		}else if($orderStatus==-2){
			$name='PendingPayorder';
		}else if($orderStatus==1){
			$name='DistributionOrder';
		}else if($orderStatus==-1){
			$name='CancelOrder';
		}else if($orderStatus==-3){
			$name='RejectionOrder';
		}else if($orderStatus==2){
			$name='ReceivedOrder';
		}else if($orderStatus==10000){
			$name='CancelOrder/RejectionOrder';
		}else if($orderStatus==20000){
			$name='PendingRecOrder';
		}
		$name = $name.date('Ymd');
		$shopId = (int)session('WST_USER.shopId');
		$where = ['o.shopId'=>$shopId];
		$orderNo = input('orderNo');
		$shopName = input('shopName');
		
		$type = (int)input('type',-1);
		$payType = $type>0?$type:(int)input('payType',-1);
		$deliverType = (int)input('deliverType');
		if($orderStatus == 10000)$orderStatus = [-1,-3];
		if($orderStatus == 20000)$orderStatus = [0,1];
		if(is_array($orderStatus)){
			$where[] = ['o.orderStatus','in',$orderStatus];
		}else{
			$where['o.orderStatus'] = $orderStatus;
		}
		if($orderNo!=''){
			$where[] = ['orderNo','like',"%$orderNo%"];
		}
		if($shopName!=''){
			$where[] = ['shopName','like',"%$shopName%"];
		}
		if($payType > -1){
			$where['payType'] =  $payType;
		}
		if($deliverType > -1){
			$where['deliverType'] =  $deliverType;
		}
		$page = $this->alias('o')->where($where)->join('__SHOPS__ s','o.shopId=s.shopId','left')
		->join('__ORDER_REFUNDS__ orf','orf.orderId=o.orderId and refundStatus=0','left')
		->join('__LOG_ORDERS__ lo','lo.orderId=o.orderId and lo.orderStatus in (-1,-3) ','left')
		->field('o.orderId,orderNo,goodsMoney,totalMoney,realTotalMoney,o.orderStatus,deliverType,deliverMoney,isAppraise,o.deliverMoney,lo.logContent,o.payTime,o.payFrom
		,o.invoiceJson,o.isInvoice,o.isRefund,payType,o.userName,o.userAddress,o.userPhone,o.orderRemarks,o.invoiceClient,o.receiveTime,o.deliveryTime,orderSrc,o.createTime,orf.id refundId')
		->order('o.createTime', 'desc')
		->select();
		if(count($page)>0){
			foreach ($page as $v){
				$orderIds[] = $v['orderId'];
			}
			$goods = Db::name('order_goods')->where([['orderId','in',$orderIds]])->select();
			$goodsMap = [];
			foreach ($goods as $v){
				$v['goodsSpecNames'] = str_replace('@@_@@','、',$v['goodsSpecNames']);
				$goodsMap[$v['orderId']][] = $v;
			}
			foreach ($page as $key => $v){
				$page[$key]['invoiceArr'] = '';
				if($v['isInvoice']==1){
					$invoiceArr = json_decode($v['invoiceJson'],true);
					$page[$key]['invoiceArr'] = " ".$invoiceArr['invoiceHead'];
					if(isset($invoiceArr['invoiceCode'])){
						$page[$key]['invoiceArr'] = " ".$invoiceArr['invoiceHead'].'|'.$invoiceArr['invoiceCode'];
					}
				}
				$page[$key]['payTypeName'] = WSTLangPayType($v['payType']);
				$page[$key]['deliverType'] = WSTLangDeliverType($v['deliverType']==1);
				$page[$key]['status'] = WSTLangOrderStatus($v['orderStatus']);
				$page[$key]['goods'] = $goodsMap[$v['orderId']];
			}
		}
		require Env::get('root_path') . 'extend/phpexcel/PHPExcel.php';
		
		$objPHPExcel = new \PHPExcel();
		// 设置excel文档的属性
		$objPHPExcel->getProperties()->setCreator("WSTMart")//创建人
		->setLastModifiedBy("WSTMart")//最后修改人
		->setTitle($name)//标题
		->setSubject($name)//题目
		->setDescription($name)//描述
		->setKeywords("订单")//关键字
		->setCategory("Test result file");//种类
	
		// 开始操作excel表
		$objPHPExcel->setActiveSheetIndex(0);
		// 设置工作薄名称
		$objPHPExcel->getActiveSheet()->setTitle(iconv('gbk', 'utf-8', 'Sheet'));
		// 设置默认字体和大小
		$objPHPExcel->getDefaultStyle()->getFont()->setName(iconv('gbk', 'utf-8', ''));
		$objPHPExcel->getDefaultStyle()->getFont()->setSize(11);
		$styleArray = array(
				'font' => array(
						'bold' => true,
						'color'=>array(
								'argb' => 'ffffffff',
						)
				),
				'borders' => array (
						'outline' => array (
								'style' => \PHPExcel_Style_Border::BORDER_THIN,  //设置border样式
								'color' => array ('argb' => 'FF000000'),     //设置border颜色
						)
				)
		);
		
		//设置宽
		$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(12);
		$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(12);
		$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(12);
		$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(30);
		$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(12);
		$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(12);
		$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(12);
		$objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(20);
		$objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(12);
		$objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(20);
		$objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(50);
		$objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(20);
		$objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(20);
		$objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(20);
		$objPHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(20);
		$objPHPExcel->getActiveSheet()->getColumnDimension('P')->setWidth(20);
		$objPHPExcel->getActiveSheet()->getColumnDimension('Q')->setWidth(20);
		$objPHPExcel->getActiveSheet()->getColumnDimension('R')->setWidth(20);
		$objPHPExcel->getActiveSheet()->getColumnDimension('S')->setWidth(20);
		$objPHPExcel->getActiveSheet()->getColumnDimension('T')->setWidth(20);
		$objPHPExcel->getActiveSheet()->getColumnDimension('U')->setWidth(50);
		$objPHPExcel->getActiveSheet()->getColumnDimension('V')->setWidth(10);
		$objPHPExcel->getActiveSheet()->getStyle('A1:V1')->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID);
		$objPHPExcel->getActiveSheet()->getStyle('A1:V1')->getFill()->getStartColor()->setARGB('333399');
		$objPHPExcel->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
		$objPHPExcel->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
		
		$objPHPExcel->getActiveSheet()->setCellValue('A1', '订单编号')->setCellValue('B1', '订单状态')->setCellValue('C1', '收货人')->setCellValue('D1', '收货地址')->setCellValue('E1', '联系方式')
		->setCellValue('F1', '支付方式')->setCellValue('G1', '支付来源')->setCellValue('H1', '配送方式')->setCellValue('I1', '买家留言')->setCellValue('J1', '发票信息')
		->setCellValue('K1', '订单商品')->setCellValue('L1', '商品价格')->setCellValue('M1', '数量')->setCellValue('N1', '订单总金额')->setCellValue('O1', '运费')->setCellValue('P1', '实付金额')
		->setCellValue('Q1', '下单时间')->setCellValue('R1', '付款时间')->setCellValue('S1', '发货时间')->setCellValue('T1', '收货时间')->setCellValue('U1', '取消/拒收原因')->setCellValue('V1', '是否退款');
		$objPHPExcel->getActiveSheet()->getStyle('A1:V1')->applyFromArray($styleArray);
		$i = 1;
		for ($row = 0; $row < count($page); $row++){
			$goodsn = count($page[$row]['goods']);
			$i = $i+1;
			$i2 = $i3 = $i;
			$i = $i+(1*$goodsn)-1;
			$objPHPExcel->getActiveSheet()->mergeCells('A'.$i2.':A'.$i)->mergeCells('B'.$i2.':B'.$i)->mergeCells('C'.$i2.':C'.$i)->mergeCells('D'.$i2.':D'.$i)->mergeCells('E'.$i2.':E'.$i)->mergeCells('F'.$i2.':F'.$i)
			->mergeCells('G'.$i2.':G'.$i)->mergeCells('H'.$i2.':H'.$i)->mergeCells('I'.$i2.':I'.$i)->mergeCells('J'.$i2.':J'.$i)->mergeCells('N'.$i2.':N'.$i)->mergeCells('O'.$i2.':O'.$i)
			->mergeCells('P'.$i2.':P'.$i)->mergeCells('Q'.$i2.':Q'.$i)->mergeCells('R'.$i2.':R'.$i)->mergeCells('S'.$i2.':S'.$i)->mergeCells('T'.$i2.':T'.$i)->mergeCells('U'.$i2.':U'.$i)->mergeCells('V'.$i2.':V'.$i);
			$objPHPExcel->getActiveSheet()->setCellValue('A'.$i2, $page[$row]['orderNo'])->setCellValue('B'.$i2, $page[$row]['status'])->setCellValue('C'.$i2, $page[$row]['userName'])->setCellValue('D'.$i2, $page[$row]['userAddress'])
			->setCellValue('E'.$i2, $page[$row]['userPhone'])->setCellValue('F'.$i2, $page[$row]['payTypeName'])->setCellValue('G'.$i2, ($page[$row]['payFrom'])?WSTLangPayFrom($page[$row]['payFrom']):'')->setCellValue('H'.$i2, $page[$row]['deliverType'])
			->setCellValue('I'.$i2, $page[$row]['orderRemarks'])->setCellValue('J'.$i2, $page[$row]['invoiceArr'])->setCellValue('N'.$i2, $page[$row]['totalMoney'])->setCellValue('O'.$i2, $page[$row]['deliverMoney'])->setCellValue('P'.$i2, $page[$row]['realTotalMoney'])
			->setCellValue('Q'.$i2, $page[$row]['createTime'])->setCellValue('R'.$i2, $page[$row]['payTime'])->setCellValue('S'.$i2, $page[$row]['deliveryTime'])->setCellValue('T'.$i2, $page[$row]['receiveTime'])
			->setCellValue('U'.$i2, $page[$row]['logContent'])->setCellValue('V'.$i2, ($page[$row]['isRefund']==1)?'是':'');
			$objPHPExcel->getActiveSheet()->getStyle('D'.$i2)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
			$objPHPExcel->getActiveSheet()->getStyle('U'.$i2)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
			for ($row2 = 0; $row2 < $goodsn; $row2++){
				$objPHPExcel->getActiveSheet()->setCellValue('K'.$i3, (($page[$row]['goods'][$row2]['goodsCode']=='gift')?'【赠品】':'').$page[$row]['goods'][$row2]['goodsName'])->setCellValue('L'.$i3, $page[$row]['goods'][$row2]['goodsPrice'])->setCellValue('M'.$i3, $page[$row]['goods'][$row2]['goodsNum']);
				$objPHPExcel->getActiveSheet()->getStyle('K'.$i3)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
				$i3 = $i3 + 1;
			}
		}
	
		//输出EXCEL格式
		$objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		// 从浏览器直接输出$filename
		header('Content-Type:application/csv;charset=UTF-8');
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
		header("Content-Type:application/force-download");
		header("Content-Type:application/vnd.ms-excel;");
		header("Content-Type:application/octet-stream");
		header("Content-Type:application/download");
		header('Content-Disposition: attachment;filename="'.$name.'.xls"');
		header("Content-Transfer-Encoding:binary");
		$objWriter->save('php://output');
	}
	
	
	public function addPayLog($txt){
		$logOrder = [];
		$logOrder['txt'] = $txt;
		$logOrder['logTime'] = date('Y-m-d H:i:s');
		Db::name('pay_log')->insert($logOrder);
	}

	/**
	 * 余额支付
	 */
	public function payByWallet($uId=0,$type=0){
		$payPwd = input('payPwd');
		if(!$payPwd)return WSTReturn('请输入密码',-1);
		if($uId==0 || $type == 1){// 大于0表示来自app端
			$decrypt_data = WSTRSA($payPwd);
			if($decrypt_data['status']==1){
				$payPwd = $decrypt_data['data'];
			}else{
				return WSTReturn('支付失败');
			}
		}
        $key = input('key');
        $key = WSTBase64url($key,false);
        $base64 = new \org\Base64();
        $key = $base64->decrypt($key,"WSTMart");
        $key = explode('_',$key);
        if(count($key)>1){
        	$orderNo = $key[0];
        	$isBatch = (int)$key[1];
        }else{
        	$orderNo = input('orderNo');
        	$isBatch = (int)input('isBatch');
        }
        $userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
        //判断是否开启余额支付
        $isEnbalePay = model('Payments')->isEnablePayment('wallets');
        if($isEnbalePay==0)return WSTReturn('非法的支付方式',-1);
        //判断订单状态
        $where = ["userId"=>$userId,"dataFlag"=>1,"orderStatus"=>-2,"isPay"=>0,"payType"=>1];
		if($isBatch==1){
			$where['orderunique'] = $orderNo;
		}else{
			$where['orderNo'] = $orderNo;
		}
		$orders = $this->field('orderId,orderNo,orderType,needPay,shopId,payFrom,realTotalMoney')->where($where)->select();
		if(count($orders)==0)return WSTReturn('您的订单已支付',-1);
		//判断订单金额是否正确
		$needPay = 0;
		foreach ($orders as $v) {
			$needPay += $v->needPay;
		}
	    //获取用户钱包
	    $user = model('users')->get(['userId'=>$userId]);
	    if($user->payPwd=='')return WSTReturn('您未设置支付密码，请先设置密码',-1);
	    if($user->payPwd!=md5($payPwd.$user->loginSecret))return WSTReturn('您的支付密码不正确',-1);
		if($needPay > $user->userMoney)return WSTReturn('您的钱包余额不足',-1);
		$userMoney = $user->userMoney;
		$rechargeMoney = $user->rechargeMoney;
		Db::startTrans();
		try{
            //循环处理每个订单
            foreach ($orders as $order) {
            	//处理订单信息
            	$tmpNeedPay = $order->needPay;
            	$lockCashMoney = ($rechargeMoney>$tmpNeedPay)?$tmpNeedPay:$rechargeMoney;
            	$order->needPay = 0;
            	$order->isPay = 1;
            	$order->payTime = date('Y-m-d H:i:s');
            	$order->orderStatus = 0;
            	$order->payFrom = 'wallets';
            	$order->lockCashMoney = $lockCashMoney;
            	$result = $order->save();
                if(false != $result){
                
                	$shop = model('shops')->get(['shopId'=>$order->shopId]);
					//新增订单日志
					$logOrder = [];
					$logOrder['orderId'] = $order->orderId;
					$logOrder['orderStatus'] = 0;
					$logOrder['logContent'] = "订单已支付,下单成功";
					$logOrder['logUserId'] = $userId;
					$logOrder['logType'] = 0;
					$logOrder['logTime'] = date('Y-m-d H:i:s');
					Db::name('log_orders')->insert($logOrder);

                    //创建一条支出流水记录
					$lm = [];
					$lm['targetType'] = 0;
					$lm['targetId'] = $userId;
					$lm['dataId'] = $order->orderId;
					$lm['dataSrc'] = 1;
					$lm['remark'] = '交易订单【'.$order->orderNo.'】支出¥'.$tmpNeedPay;
					$lm['moneyType'] = 0;
					$lm['money'] = $tmpNeedPay;
					$lm['payType'] = 'wallets';
					model('LogMoneys')->add($lm);
					//修改用户充值金额
					model('users')->where(["userId"=>$userId])->setDec("rechargeMoney",$lockCashMoney);
					//虚拟商品处理
	                if($order->orderType==1){
	                    $this->handleVirtualGoods($order->orderId);
	                }else{
	                    //发送一条商家信息
						$tpl = WSTMsgTemplates('ORDER_HASPAY');
				        if( $tpl['tplContent']!='' && $tpl['status']=='1'){
				            $find = ['${ORDER_NO}'];
				            $replace = [$order->orderNo];
				            //WSTSendMsg($shop->userId,$msgContent,['from'=>1,'dataId'=>$order->orderId]);
				            $msg = array();
				            $msg["shopId"] = $order->shopId;
				            $msg["tplCode"] = $tpl["tplCode"];
				            $msg["msgType"] = 1;
				            $msg["content"] = str_replace($find,$replace,$tpl['tplContent']);
				            $msg["msgJson"] = ['from'=>1,'dataId'=>$order->orderId];
				            model("common/MessageQueues")->add($msg);
				        } 
						
						//判断是否需要发送管理员短信
		                $tpl = WSTMsgTemplates('PHONE_ADMIN_PAY_ORDER');
		                if((int)WSTConf('CONF.smsOpen')==1 && (int)WSTConf('CONF.smsPayOrderTip')==1 &&  $tpl['tplContent']!='' && $tpl['status']=='1'){
							$params = ['tpl'=>$tpl,'params'=>['ORDER_NO'=>$order->orderNo]];
							$staffs = Db::name('staffs')->where([['staffId','in',explode(',',WSTConf('CONF.payOrderTipUsers'))],['staffStatus','=',1],['dataFlag','=',1]])->field('staffPhone')->select();
							for($i=0;$i<count($staffs);$i++){
								if($staffs[$i]['staffPhone']=='')continue;
								$m = new LogSms();
					            $rv = $m->sendAdminSMS(0,$staffs[$i]['staffPhone'],$params,'payByWallet','');
					        }
		                }
						//微信消息
					    if((int)WSTConf('CONF.wxenabled')==1){
						    $params = [];
						    $params['ORDER_NO'] = $order->orderNo;
					        $params['PAY_TIME'] = date('Y-m-d H:i:s');             
						    $params['MONEY'] = $order->realTotalMoney;
						    $params['PAY_SRC'] = WSTLangPayFrom($order->payFrom);
				            //WSTWxMessage(['CODE'=>'WX_ORDER_PAY','userId'=>$shop->userId,'URL'=>Url('wechat/orders/sellerorder','',true,true),'params'=>$params]);
					        $msg = array();
							$tplCode = "WX_ORDER_PAY";
							$msg["shopId"] = $order->shopId;
				            $msg["tplCode"] = $tplCode;
				            $msg["msgType"] = 4;
				            $msg["paramJson"] = ['CODE'=>$tplCode,'URL'=>Url('wechat/orders/sellerorder','',true,true),'params'=>$params];
				            $msg["msgJson"] = "";
				            model("common/MessageQueues")->add($msg);

					        //判断是否需要发送给管理员消息
			                if((int)WSTConf('CONF.wxPayOrderTip')==1){
			                	$params['ORDER_NO'] = $order->orderNo;
						        $params['PAY_TIME'] = date('Y-m-d H:i:s');             
							    $params['MONEY'] = $order->realTotalMoney;
							    $params['PAY_SRC'] = WSTLangPayFrom($order->payFrom);
				            	WSTWxBatchMessage(['CODE'=>'WX_ADMIN_ORDER_PAY','userType'=>3,'userId'=>explode(',',WSTConf('CONF.payOrderTipUsers')),'params'=>$params]);
			                }
					    }
	                }

                }
            }
			Db::commit();
			return WSTReturn('订单支付成功',1);
		}catch (\Exception $e) {
			Db::rollback();
			return WSTReturn('订单支付失败');
		}
	}

	/**
	 * 获取订单金额以及用户钱包金额
	 */
	public function getOrderPayInfo($obj){
        $userId = (int)$obj["userId"];
		$orderNo = $obj["orderNo"];
		$isBatch = (int)$obj["isBatch"];
		$needPay = 0;
		$where = ["userId"=>$userId,"dataFlag"=>1,"orderStatus"=>-2,"isPay"=>0,"payType"=>1];
		$where[] = ["needPay",">",0];
		if($isBatch==1){
			$where['orderunique'] = $orderNo;
		}else{
			$where['orderNo'] = $orderNo;
		}
		$orders = model('orders')->where($where)->field('needPay,payRand')->select();
		if(empty($orders))return [];
		$needPay = 0;
		$payRand = 0;
		foreach($orders as $order){
            $needPay += $order['needPay'];
            if($payRand<$order['payRand'])$payRand = $order['payRand'];
		}
		$data = array();
		$data["needPay"] = $needPay;
		$data["payRand"] = $payRand;
		return $data;
	}
	
	public function getOrderPayFrom($out_trade_no){
		$rs = $this->where(['dataFlag'=>1,'orderNo|orderunique'=>$out_trade_no])->field('orderId,userId,orderNo,orderunique')->find();
		if(!empty($rs)){
			$rs['isBatch'] = ($rs['orderunique'] == $out_trade_no)?1:0;
		}
		return $rs;
	}

	/*
	 * 查询订单状态
	 */
	public function getOrder($where)
    {
        $orderInfo = $this->where($where)->find();
        if (!$orderInfo) {
            throw AE::factory(AE::ORDER_NOT_EXISTS);
        }
        return $orderInfo;
    }

	/**
	* 用户-提醒发货
	*/
	public function noticeDeliver($uId=0){
		$orderId = (int)input('id');
		$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
		Db::startTrans();
		try{
			$rs = $this->where(['userId'=>$userId,'orderId'=>$orderId])->setField('noticeDeliver',1);
			if($rs!==false){
				$info = $this->alias('o')->field('shopId,orderNo')->where(['userId'=>$userId,'orderId'=>$orderId])->find();
				//发送商城消息提醒卖家
				$tpl = WSTMsgTemplates('ORDER_REMINDER');
                if( $tpl['tplContent']!='' && $tpl['status']=='1'){
                    $find = ['${LOGIN_NAME}','${ORDER_NO}'];
                    $replace = [session('WST_USER.loginName'),$info['orderNo']];
                    
                    $msg = array();
		            $msg["shopId"] = $info['shopId'];
		            $msg["tplCode"] = $tpl["tplCode"];
		            $msg["msgType"] = 1;
		            $msg["content"] = str_replace($find,$replace,$tpl['tplContent']);
		            $msg["msgJson"] = [];
		            model("common/MessageQueues")->add($msg);
                }
			}
			Db::commit();
			return WSTReturn('提醒成功',1);
		}catch(\Exception $e){
			Db::rollback();
		}
		return WSTReturn('提醒失败',-1);
	}

	public function updateOrderData($orderId, $data)
    {
        $res = $this->where(['orderId'=>$orderId])->update($data);
        if (!$res) throw AE::factory(AE::REFUND_INSERT_FAIL);
        return $res;
    }
	
}
