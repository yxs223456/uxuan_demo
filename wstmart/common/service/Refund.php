<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/7/20
 * Time: 13:59
 */

namespace wstmart\common\service;

use wstmart\app\service\Users as ASUser;
use think\Db;
use wstmart\common\model\Orders as O;
use wstmart\common\exception\AppException as AE;
use wstmart\common\model\OrderRefunds as OF;
use wstmart\common\model\Pintuans as p;
use wstmart\common\model\Express as E;
use wstmart\common\model\Shops as S;
use wstmart\common\service\News;

class Refund
{
    public function refundFilter($refundId, $type, $goodsStatus, $refundReason, $refundTotal, $refundExplain, $refundPhone, $refundImg, $refundOrderId)
    {
        $userId = ASUser::getUserByCache()['userId'];
        //查询订单是否存在并且已支付
        $o = new O();
        $p = new P();
        $orderInfo = $o->getOrderById($refundOrderId);
        if ($orderInfo->userId!=$userId) throw AE::factory(AE::GOODS_APPRAISES_INVALID);
        if ($orderInfo->isPay!=1) throw AE::factory(AE::ORDER_STATUS_NOT_EXISTS);
        if ($orderInfo->orderStatus>2 || $orderInfo->orderStatus<0) throw AE::factory(AE::REFUND_STATUS_NOT_EXISTS);
        if ($orderInfo->orderStatus==2 && !empty($orderInfo->receiveTime)) {
            $collectGoodsTime = strtotime($orderInfo->receiveTime);
            if ((time()-$collectGoodsTime)>config('web.refund_time')) throw AE::factory(AE::REFUND_APPLY_OVERTIME);
        }
        if ($orderInfo->isPintuan==1) {
            $map = ['orderId'=>$refundOrderId, 'userId'=>$userId];
            $pintuanInfo = $p->getPintuanInfoByOrderId($map);
            if ($pintuanInfo['tuanStatus']!=2) {
                throw AE::factory(AE::ORDER_PINTUAN_REFUSE);
            }
        }
        if (isset($refundExplain) && mb_strlen($refundExplain)>250) throw AE::factory(AE::WORD_OVERRUN);
        if ($refundTotal>$orderInfo->realTotalMoney) throw AE::factory(AE::REFUND_TOTAL_MAX);
        $refundData= [
            'orderId'=>$refundOrderId,
            'shopId'=>$orderInfo['shopId'],
            'refundTo'=>$userId,
            'refundReson'=>$refundReason,
            'backMoney'=>$refundTotal,
            'refundTradeNo'=>'',
            'refundImage'=>$refundImg,
            'refundPhone'=>$refundPhone,
            'refundRemark'=>$refundExplain,
            'goodsStatus'=>$goodsStatus,
            'type'=>$type,
            'refundStatus'=>0,
            'createTime'=>date('Y-m-d H:i:s')
        ];
        Db::startTrans();
        try {
            $of = new OF();
            if ($refundId>0) {
                $refundInfo = $of->getOrderRefunInfo(['id'=>$refundId]);
                if ($refundInfo->refundStatus==-1 && (time()-strtotime($refundInfo->shopConsentTime))>7*86400) throw AE::factory(AE::REFUND_SATATUS_UPDATE_OUT_TIME);
                $rs = $of->updateRefundData(['id'=>$refundId], $refundData);
            } else {
                $res = $of->getOrderRefunInfo(['refundTo'=>$userId, 'orderId'=>$refundOrderId]);
                if(!empty($res) || $orderInfo->afterSaleStatus==1) throw AE::factory(AE::REFUND_ORDER_NOT_AGAIN);
                $rs = $of->addRefundData($refundData);
                if (!$rs) throw AE::factory(AE::REFUND_INSERT_FAIL);
                $o->updateOrderData($refundOrderId, ['afterSaleStatus'=>1]);
            }
            Db::commit();
            (new News())->refundApply($rs);
            return ['refundId'=>$rs];
        } catch (\Throwable $e) {
            Db::rollback();
            throw  $e;
        }
    }

    public function refundDefaultData($orderId)
    {
        $o = new O();
        $orderInfo = $o->getOrderById($orderId);
        $data['realTotalMoney'] = (float) $orderInfo->realTotalMoney;
        $data['orderId'] = $orderId;
        $data['userPhone'] = $orderInfo->userPhone;
        return $data;
    }

    public function getRefundInfo($refundId, $userId)
    {
        $of = new OF();
        $s = new S();
        $refundInfo = $of->getRefundInfo($refundId, $userId);
        $shopInfo = $s->getFieldsById($refundInfo['shopId'], 'shopAddress');
        $refundInfo['refundReason'] = $of->getRefundResaonName($refundInfo['refundReson'])['dataName'];
        $refundInfo['type'] = ($refundInfo['type']==1) ? '退货退款' : '仅退款';
        $refundInfo['shopAddress'] = $shopInfo->shopAddress;
        $refundInfo['refundStatus'] = array_search($refundInfo['refundStatus'], config('web.refund_status'));
        return $refundInfo;
    }

    /*
     * 撤销退款申请
     */
    public function updateRefundStatus($refundId)
    {
        $userId =  ASUser::getUserByCache()['userId'];
        $map['id']= $refundId;
        $data['refundStatus'] = 3;
        $of = new OF();
        $o = new O();
        Db::startTrans();
        try {
            $refundInfo = $of->getRefundDetails(['id'=>$refundId, 'refundTo'=>$userId]);
            if ($refundInfo->refundStatus!==0) throw AE::factory(AE::REFUND_ORDER_REVOKE);
            $of->updateRefundData($map, $data);
            $o->updateOrderData($refundInfo->orderId, ['afterSaleStatus'=>5]);
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            throw  $e;
        }
    }

    public function moneyWhereAbouts($refundId)
    {
        $userId =  ASUser::getUserByCache()['userId'];
        $of = new OF();
        $status = $of->getOrderRefundData($refundId);
        if ($status==1) {
            $res = $of->getmoneyWhereAbouts($userId, $refundId);
            $res['refund_fee'] = bcdiv($res['refund_fee'],100,2);
            $res['payType'] = (strpos($res['payFrom'], 'weixinpays')!==false) ? '微信支付' : '其他';
            unset($res['payFrom']);
        }else {
            throw AE::factory(AE::REFUND_SATATUS_NOT_SUPPORT);
        }
        return $res;
    }

    public function insertExpressNo($refundId, $expressName, $expressNo)
    {
        $userId =  ASUser::getUserByCache()['userId'];
        $map['id']= $refundId;
        $map['refundTo']= $userId;
        $of = new OF();
        $data = [
            'expressId'=>($expressName=='其它') ? -1 : $of->getExpressInfo(['expressName'=>$expressName])['expressId'],
            'expressNo'=>$expressNo
        ];
        $of->updateRefundData($map, $data);
        return true;
    }

    public function selectExpressNo($refundId)
    {
        $userId =  ASUser::getUserByCache()['userId'];
        $of = new OF();
        $res = $of->getOrderRefunInfo(['refundTo'=>$userId, 'id'=>$refundId]);
        if (!$res) throw AE::factory(AE::REFUND_INFO_EMPTY);
        $data['refundId'] = $res->id;
        $data['expressName'] = $res->expressId==-1 ? '其它' : $of->getExpressInfo(['expressId'=>$res->expressId])['expressName'] ?? '';
        $data['expressNo'] = $res->expressNo ?? '';
        return $data;
    }

    public function expressList()
    {
        $e = new E();
        $expressList = $e->listQuery();
        $list = [];
        foreach ($expressList as $k=>$v) {
            $list[$k]['expressId'] = $v['expressId'];
            $list[$k]['expressName'] = $v['expressName'];
        }
        return $list;
    }

}