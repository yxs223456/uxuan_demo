<?php
namespace wstmart\common\model;

use think\Db;
use wstmart\common\exception\AppException as AE;
use wstmart\common\service\Orders as O;
use wstmart\common\model\Orders as MO;
use wstmart\common\service\News;

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
 * 退款业务处理类
 */
class OrderRefunds extends Base{

    public function getOrderRefundModelById($id)
    {
        $model = $this->where('id', $id)->find();
        if (empty($model)) {
            throw AE::factory(AE::ORDER_REFUND_NOT_EXISTS);
        }
        return $model;
    }

    public function getRefundDetails($where)
    {
        $rs = $this->where($where)->find();
        if (!$rs) throw AE::factory(AE::ORDER_REFUND_NOT_EXISTS);
        return $rs;
    }

	/**
	 * 用户申请退款
	 */
	public function refund($uId=0){
		$orderId = (int)input('post.id');
		$reason = (int)input('post.reason');
		$content = input('post.content');
		$money = (float)input('post.money');
		$userId = ($uId==0)?(int)session('WST_USER.userId'):$uId;
		if($money<0)return WSTReturn("退款金额不能为负数");
		$order = Db::name('orders')->alias('o')->join('__ORDER_REFUNDS__ orf','orf.orderId=o.orderId','left')->join('__SHOPS__ s','o.shopId=s.shopId','left')
		           ->where([['o.orderStatus','in',[-3,-1]],['o.orderId','=',$orderId],['o.userId','=',$userId],['isRefund','=',0]])
		           ->field('o.orderId,s.userId,o.shopId,o.orderStatus,o.orderNo,o.realTotalMoney,o.isPay,o.payType,o.useScore,orf.id refundId')->find();
		$reasonData = WSTDatas('REFUND_TYPE',$reason);
		if(empty($reasonData))return WSTReturn("无效的退款原因");
		if($reason==10000 && $content=='')return WSTReturn("请输入退款原因");
		if(empty($order))return WSTReturn('操作失败，请检查订单是否符合申请退款条件');
		$allowRequest = false;
		if($order['isPay']==1 || $order['useScore']>0){
			$allowRequest = true;
		}
		if(!$allowRequest)return WSTReturn("您的退款申请已提交，请留意退款信息");
		if($money>$order['realTotalMoney'])return WSTReturn("申请退款金额不能大于实支付金额");
		//查看退款申请是否已存在
		$orfId = $this->where('orderId',$orderId)->value('id');
		Db::startTrans();
		try{
			$result = false;
			//如果退款单存在就进行编辑
			if($orfId>0){
				$object = $this->get($orfId);
				$object->refundReson = $reason;
				if($reason==10000)$object->refundOtherReson = $content;
				$object->backMoney = $money;
				$object->refundStatus = ($order['orderStatus']==-1)?1:0;;
				$result = $object->save();
			}else{
				$data = [];
				$data['orderId'] = $orderId;	
	            $data['refundTo'] = 0;
	            $data['refundReson'] = $reason;
	            if($reason==10000)$data['refundOtherReson'] = $content;
	            $data['backMoney'] = $money;
	            $data['createTime'] = date('Y-m-d H:i:s');
	            $data['refundStatus'] = ($order['orderStatus']==-1)?1:0;
	            $result = $this->save($data);
			}			
            if(false !== $result){
            	//拒收、取消申请退款的话要给商家发送信息
            	if($order['orderStatus']!=-1){
            		$tpl = WSTMsgTemplates('ORDER_REFUND_CONFER');
	                if( $tpl['tplContent']!='' && $tpl['status']=='1'){
	                    $find = ['${ORDER_NO}'];
	                    $replace = [$order['orderNo']];
	                    
	                	$msg = array();
			            $msg["shopId"] = $order['shopId'];
			            $msg["tplCode"] = $tpl["tplCode"];
			            $msg["msgType"] = 1;
			            $msg["content"] = str_replace($find,$replace,$tpl['tplContent']);
			            $msg["msgJson"] = ['from'=>1,'dataId'=>$orderId];
			            model("common/MessageQueues")->add($msg);
	                }
	                 
	                //微信消息
					if((int)WSTConf('CONF.wxenabled')==1){
						$params = [];
						$params['ORDER_NO'] = $order['orderNo'];
					    $params['REASON'] = $reasonData['dataName'].(($reason==10000)?" - ".$content:"");             
						$params['MONEY'] = $money.(($order['useScore']>0)?("【退回积分：".$order['useScore']."】"):"");
				       
						$msg = array();
						$tplCode = "WX_ORDER_REFUND_CONFER";
						$msg["shopId"] = $order['shopId'];
			            $msg["tplCode"] = $tplCode;
			            $msg["msgType"] = 4;
			            $msg["paramJson"] = ['CODE'=>$tplCode,'URL'=>Url('wechat/orders/sellerorder','',true,true),'params'=>$params];
			            $msg["msgJson"] = "";
			            model("common/MessageQueues")->add($msg);
					} 
			    }else{
			    	//判断是否需要发送管理员短信
					$tpl = WSTMsgTemplates('PHONE_ADMIN_REFUND_ORDER');
					if((int)WSTConf('CONF.smsOpen')==1 && (int)WSTConf('CONF.smsRefundOrderTip')==1 &&  $tpl['tplContent']!='' && $tpl['status']=='1'){
						$params = ['tpl'=>$tpl,'params'=>['ORDER_NO'=>$order['orderNo']]];
						$staffs = Db::name('staffs')->where([['staffId','in',explode(',',WSTConf('CONF.refundOrderTipUsers'))],['staffStatus','=',1],['dataFlag','=',1]])->field('staffPhone')->select();
						for($i=0;$i<count($staffs);$i++){
							if($staffs[$i]['staffPhone']=='')continue;
							$m = new LogSms();
							$rv = $m->sendAdminSMS(0,$staffs[$i]['staffPhone'],$params,'refund','');
						}
					}
					//微信消息
					if((int)WSTConf('CONF.wxenabled')==1){
						//判断是否需要发送给管理员消息
		                if((int)WSTConf('CONF.wxRefundOrderTip')==1){
		                	$params = [];
						    $params['ORDER_NO'] = $order['orderNo'];
					        $params['REASON'] = $reasonData['dataName'].(($reason==10000)?" - ".$content:"");             
						    $params['MONEY'] = $money.(($order['useScore']>0)?("【退回积分：".$order['useScore']."】"):"");
			            	WSTWxBatchMessage(['CODE'=>'WX_ADMIN_ORDER_REFUND','userType'=>3,'userId'=>explode(',',WSTConf('CONF.refundOrderTipUsers')),'params'=>$params]);
		                }
					}
			    }
            	Db::commit();
                return WSTReturn('您的退款申请已提交，请留意退款信息',1);
            }
		}catch (\Exception $e) {
		    Db::rollback();
	    }
	    return WSTReturn('操作失败',-1);
	}

	/**
	 * 获取订单价格以及申请退款价格
	 */
	public function getRefundMoneyByOrder($orderId = 0){
		return Db::name('orders')->alias('o')->join('__ORDER_REFUNDS__ orf','orf.orderId=o.orderId')->where('orf.id',$orderId)->field('o.orderId,orderNo,goodsMoney,deliverMoney,useScore,scoreMoney,totalMoney,realTotalMoney,orf.backMoney')->find();
	}
    /**
     * 商家处理是否同意退款
     */
    public function shopRefund(){
        $id = (int)input('id');
        $refundStatus = (int)input('refundStatus');
        $content = input('content');
        if($id==0)return WSTReturn('无效的操作');
        if(!in_array($refundStatus,[1,-1]))return WSTReturn('无效的操作');
        if($refundStatus==-1 && $content=='')return WSTReturn('请输入拒绝原因');
        Db::startTrans();
        try{
            $object = $this->get($id);
            if ($object->refundStatus == 3) {
                throw AE::factory(AE::ORDER_REFUND_CANCEL_ALREADY);
            }
            if ($object->refundStatus != 0 && $object->refundStatus != 4) {
                throw AE::factory(AE::ORDER_REFUND_STATUS_CHANGE);
            }
            $object->refundStatus = $refundStatus;
            if($object->refundStatus==-1)$object->shopRejectReason = $content;
            $orderModel = (new MO())->getOrderById($object->orderId);
            $orderModel->afterSaleStatus = $refundStatus == 1 ? 2 : 3;
            $orderModel->save();
            $orderService = new O();
            if ($refundStatus == 1) {
                $orderService->dealOrderRefund($id);
                $object->shopConsentTime = date('Y-m-d H:i:s');
            }
            $object->save();
            Db::commit();
            $orderService->afterSaleAuditNotify($orderModel, $object);
            (new News())->refundAuditNotify($orderModel, $object);
            return WSTReturn('操作成功',1);
        }catch (\Exception $e) {
            Db::rollback();
            return WSTReturn($e->getMessage(),-1);
        }
    }

	/**
	 * 商家处理是否同意退款//商家原版
	 */
	/*public function shoprefund(){
        $id = (int)input('id');
        $refundStatus = (int)input('refundStatus');
        $content = input('content');
        if($id==0)return WSTReturn('无效的操作');
        if(!in_array($refundStatus,[1,-1]))return WSTReturn('无效的操作');
        if($refundStatus==-1 && $content=='')return WSTReturn('请输入拒绝原因');
        Db::startTrans();
        try{
        	$object = $this->get($id);
            $object->refundStatus = $refundStatus;
            if($object->refundStatus==-1)$object->shopRejectReason = $content;
            $result = $object->save();
            if(false !== $result){
            	//如果是拒收话要给用户发信息
            	$order = Db::name('orders')->where('orderId',$object->orderId)->field('userId,orderNo,orderId,useScore')->find();
            	if($refundStatus==-1){
            		$tpl = WSTMsgTemplates('ORDER_REFUND_FAIL');
	                if( $tpl['tplContent']!='' && $tpl['status']=='1'){
	                    $find = ['${ORDER_NO}','${REASON}'];
	                    $replace = [$order['orderNo'],$content];
	                    WSTSendMsg($order['userId'],str_replace($find,$replace,$tpl['tplContent']),['from'=>1,'dataId'=>$order['orderId']]);
	                } 
	                //微信消息
					if((int)WSTConf('CONF.wxenabled')==1){
						$reasonData = WSTDatas('REFUND_TYPE',$object->refundReson);
						$params = [];
						$params['ORDER_NO'] = $order['orderNo'];
					    $params['REASON'] = $reasonData['dataName'].(($object->refundReson==10000)?" - ".$object->refundOtherReson:"");
					    $params['SHOP_REASON'] = $object->shopRejectReason;             
						$params['MONEY'] = $object->backMoney.(($order['useScore']>0)?("【退回积分：".$order['useScore']."】"):"");
				        WSTWxMessage(['CODE'=>'WX_ORDER_REFUND_FAIL','userId'=>$order['userId'],'URL'=>Url('wechat/orders/index','',true,true),'params'=>$params]);
					}  
            	}else{
            		//判断是否需要发送管理员短信
					$tpl = WSTMsgTemplates('PHONE_ADMIN_REFUND_ORDER');
					if((int)WSTConf('CONF.smsOpen')==1 && (int)WSTConf('CONF.smsRefundOrderTip')==1 &&  $tpl['tplContent']!='' && $tpl['status']=='1'){
						$params = ['tpl'=>$tpl,'params'=>['ORDER_NO'=>$order['orderNo']]];
						$staffs = Db::name('staffs')->where([['staffId','in',explode(',',WSTConf('CONF.refundOrderTipUsers'))],['staffStatus','=',1],['dataFlag','=',1]])->field('staffPhone')->select();
						for($i=0;$i<count($staffs);$i++){
							if($staffs[$i]['staffPhone']=='')continue;
							$m = new LogSms();
							$rv = $m->sendAdminSMS(0,$staffs[$i]['staffPhone'],$params,'shoprefund','');
						}
					}
					//微信消息
					if((int)WSTConf('CONF.wxenabled')==1){
						//判断是否需要发送给管理员消息
		                if((int)WSTConf('CONF.wxRefundOrderTip')==1){
		                	$reasonData = WSTDatas('REFUND_TYPE',$object->refundReson);
		                	$params = [];
						    $params['ORDER_NO'] = $order['orderNo'];
					        $params['REASON'] = $reasonData['dataName'].(($object->refundReson==10000)?" - ".$object->refundOtherReson:"");           
						    $params['MONEY'] = $object->backMoney.(($order['useScore']>0)?("【退回积分：".$order['useScore']."】"):"");
			            	WSTWxBatchMessage(['CODE'=>'WX_ADMIN_ORDER_REFUND','userType'=>3,'userId'=>explode(',',WSTConf('CONF.refundOrderTipUsers')),'params'=>$params]);
		                }
					}
            	}
            	Db::commit();
            	return WSTReturn('操作成功',1);
            }
        }catch (\Exception $e) {
		    Db::rollback();
	    }
	    return WSTReturn('操作失败',-1);
	}*/

	public function shopRefundGoods()
    {
        $id = (int)input('id');
        $refundStatus = (int)input('refundStatus');
        $content = input('content');
        if($id==0)return WSTReturn('无效的操作');
        if(!in_array($refundStatus,[1,-1]))return WSTReturn('无效的操作');
        if($refundStatus==-1 && $content=='')return WSTReturn('请输入拒绝原因');
        Db::startTrans();
        $refundStatus = $refundStatus==1 ? 4 : $refundStatus;
        try{
            $object = $this->get($id);
            if ($object->refundStatus == 3) {
                throw AE::factory(AE::ORDER_REFUND_CANCEL_ALREADY);
            }
            if ($object->refundStatus != 0) {
                throw AE::factory(AE::ORDER_REFUND_STATUS_CHANGE);
            }
            $object->refundStatus = $refundStatus;
            if($object->refundStatus==-1)$object->shopRejectReason = $content;
            if($object->refundStatus==4)$object->shopConsentTime = date('Y-m-d H:i:s');
            $object->save();
            $orderModel = (new MO())->getOrderById($object->orderId);
            $orderModel->afterSaleStatus = $refundStatus == 4 ? 4 : 3;
            $orderModel->save();
            $orderService = new O();
            $orderService->afterSaleGoodsAuditNotify($orderModel, $object);
            Db::commit();
            (new News())->refundAuditNotify($orderModel, $object);
            return WSTReturn('操作成功',1);
        }catch (\Exception $e) {
            Db::rollback();
            return WSTReturn($e->getMessage(),-1);
        }
    }

	public function addRefundData($data)
    {
        $insertId = $this->insertGetId($data);
        if(!$insertId) throw AE::factory(AE::DATA_INSERT_FAIL);
        return $insertId;
    }

    public function updateRefundData($where, $data)
    {
        if (empty($where) || empty($data)) {
            throw AE::factory(AE::COM_PARAMS_EMPTY);
        }
        $res = $this->where($where)->update($data);
        if(!$res) throw AE::factory(AE::DATA_INSERT_FAIL);
        return $res;
    }

    public function getExpressInfo($where)
    {
        $res = Db::name('express')->where($where)->find();
        return $res;
    }

    public function getRefundInfo($orderNo, $userId)
    {
        $info = Db::name('order_refunds')->alias('or')
            ->join('orders o', 'o.orderId=or.orderId', 'left')
            ->join('order_goods og', 'og.orderId=or.orderId', 'left')
            ->where('or.refundTo', $userId)
            ->where('or.id', $orderNo)
            ->field('or.type, or.backMoney, or.shopId, or.refundReson, or.refundStatus, or.createTime, o.orderNo, og.goodsName')
            ->find();
        if (!$info) throw AE::factory(AE::REFUND_ORDER_EMPTY);
        return $info;
    }

    public function getRefundResaonName($refundResonId)
    {
        $name = Db::name('datas')->where('id', $refundResonId)->where('dataFlag', 1)->find();
        return $name;
    }

    public function getOrderRefundData($refundId)
    {
        $res =$this->where('id', $refundId)->find();
        if (!$res) throw AE::factory(AE::REFUND_REASON_FAIL);
        return $res->refundStatus;
    }

    public function getOrderRefunInfo($where)
    {
        $res = $this->where($where)->find();
        return $res;
    }

    public function getmoneyWhereAbouts($userId, $refundId)
    {
        $res = Db::name('users')->alias('u')
            ->join('order_refunds or', 'u.userId=or.refundTo', 'left')
            ->join('wx_refund_orders wro', 'wro.targetId=or.id')
            ->join('orders o', 'o.orderId=or.orderId')
            ->where('or.id', $refundId)
            ->where('refundTo', $userId)
            ->field('u.nickname, or.refundTime, or.shopConsentTime, wro.refund_fee, wro.refund_recv_accout_0, wro.refund_success_time_0, wro. refund_recv_accout_0, o.payFrom')
            ->find();
        if (!$res) throw AE::factory(AE::REFUND_WECHAT_ERROR);
        return $res;
    }
}
