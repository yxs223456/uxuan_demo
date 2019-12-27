<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/7/6
 * Time: 17:02
 */

namespace wstmart\app\service;

use think\Db;
use wstmart\common\model\UserAddress as M;
use wstmart\app\service\Users as ASUsers;
use wstmart\common\exception\AppException as AE;
use wstmart\app\model\Orders as O;
use wstmart\common\model\Orders as CO;
use wstmart\common\model\Users as U;
use wstmart\common\model\PintuanUsers as PU;
use addons\coupon\model\Coupons;
use think\config;
use wstmart\common\service\Pintuan as P;
use wstmart\app\model\GoodsAppraises as GA;
use wstmart\common\model\Coupons as C;

class Orders
{
    //订单确认页
    public function orderPrice($userId, $goodsId, $count, $isPintuan, $couponsId, $addressId, $specId, $tuanId)
    {
        $goodsInfo = model('common/Goods')->getGoodsById($goodsId);
        $price = $this->getGoodsPrice($goodsId, $specId, $isPintuan, $tuanId, $goodsInfo['shopPrice']);
        $specName = $this->filterGoodsSpec($specId);
        $data['addressInfo'] =$this->getUserAddress($userId);
        $data['goodsInfo'] = $this->filterGoodsData($goodsInfo, $price, $specName, $count);
        $data['deductibleValue'] = '';
        $couponsValue = $this->getCouponValue($userId, $couponsId, bcmul($price,$count,2), $goodsId);
        if ($couponsId>0) $data['deductibleValue'] = '抵扣'.$couponsValue.'元';
        $freight = $addressId>0 ? $this->getFreight($userId, $addressId, $goodsInfo) : 0;
        $data['goodsTotal'] = bcadd(bcmul($price,$count,2),$freight,2);
        if ($data['goodsTotal']<$couponsValue) {
            $data['payTotal'] = 0;
        } else {
            $data['payTotal'] = bcsub($data['goodsTotal'],$couponsValue,2);
        }
        $c = new Coupons();
        $coupon = $c->getCouponsByGoods($userId, $goodsId, $count, $isPintuan, $tuanId, $specId);
        $data['couponNum'] = $coupon['total'];
        $data['deliverMoney'] = $freight;
        return $data;
    }

    /*
     * 提交订单整合
     */
    public function placeOrderTrue($userId, $goodsId, $isPintuan, $goodsSpecId, $tuanId, $count)
    {
        $goodsInfo = model('common/Goods')->getGoodsById($goodsId);
        $goodsMoney = $this->getGoodsPrice($goodsId, $goodsSpecId, $isPintuan, $tuanId, $goodsInfo['shopPrice']);
        $specName = $this->filterGoodsSpec($goodsSpecId);
        $data = [];
        $c = new Coupons();
        $coupon = $c->getCouponsByGoods($userId, $goodsId, $count, $isPintuan, $tuanId, $goodsSpecId);
        $data['addressInfo'] = $this->getUserAddress($userId);
        $data['goodsInfo'] = $this->filterGoodsData($goodsInfo, $goodsMoney, $specName, $count);
        $data['deliverMoney'] = !empty($data['addressInfo']) ? $this->getFreight($userId, $data['addressInfo']['addressId'], $goodsInfo) : 0;
        $data['goodsTotal'] = bcadd(bcmul($goodsMoney,$count,2),$data['deliverMoney'],2);
        $data['payTotal'] = bcadd($data['goodsTotal'],$data['deliverMoney'],2);
        $data['couponNum'] = $coupon['total'];
        return $data;
    }

    public function getUserAddress($userId)
    {
        $m = new M();
        $default = $m->getDefault($userId);
        if (!empty($default)) {
            $addressData = $this->groupUserAddressData($default);
        } else {
            $userAddress = $m->getList($userId)->toArray();
            if ($userAddress) {
                $addressData = $this->groupUserAddressData($userAddress[0]);
            } else {
                $addressData = [];
            }
        }
        return $addressData;
    }

    public function groupUserAddressData($default)
    {
        $addressData = [
            'isDefault' => $default['isDefault'],
            'addressId' => $default['addressId'],
            'userName' => $default['userName'],
            'userPhone' => $default['userPhone'],
            'province' => $default['province'],
            'city' => $default['city'],
            'county' => $default['county'],
            'userAddress' => $default['userAddress'],
        ];
        return $addressData;
    }

    public function filterGoodsData($goodsInfo, $goodsMoney, $specName, $count)
    {
        $goods['goodsId'] = $goodsInfo['goodsId'];
        $goods['goodsName'] = $goodsInfo['goodsName'];
        $goods['goodsImg'] = addImgDomain($goodsInfo['goodsImg']);
        $goods['goodsPrice'] = $goodsMoney;
        $goods['specName'] = $specName;
        $goods['goodsNum'] = $count;
        return $goods;
    }

    public function getCouponValue($userId, $couponId, $goodsPrice, $goodsId)
    {
        $couponsValue = 0;
        if ($couponId>0){
            $c = new C();
            $couponsUserInfo = $c->getCouponsUserInfo(['userId'=>$userId, 'id'=>$couponId]);
            if ($couponsUserInfo['isUse']==1) throw AE::factory(AE::COUPONS_NO_USE_EXISTS);
            $field = 'couponValue, useCondition, useMoney, startDate, endDate, useObjects, useObjectIds';
            $couponsInfo = $c->getCouponsInfo(['couponId'=>$couponsUserInfo['couponId']], $field);
            $time = time();
            if (strtotime($couponsInfo->startDate)>$time && strtotime($couponsInfo->endDate)<$time) throw AE::factory(AE::COUPONS_TIME_EXPIRE);
            if ($couponsInfo->useCondition==1) {
                if ($couponsInfo->useMoney>$goodsPrice) throw AE::factory(AE::COUPONS_MONEY_NO_MATCH);
            }
            if ($couponsInfo->useObjects == 1) {
                $ids = explode(',',$couponsInfo->useObjectIds);
                if(!in_array($goodsId,$ids)) throw AE::factory(AE::COUPONS_NO_APPOINT);
            }
            $couponsValue = $couponsInfo->couponValue;
        }
        return $couponsValue;
    }

    public function getGoodsPrice($goodsId, $specIds, $isPintuan, $tuanId, $shopPrice)
    {
        if ($specIds>0) {
            $goodsSpec = model('common/Goods')->getGoodsSpec(['id'=>$specIds]);
            if ($goodsSpec['goodsId']!=$goodsId) throw AE::factory(AE::GOODS_SPEC_ERROR);
            $price = ($isPintuan==1) ? $goodsSpec['tuanPrice'] : $goodsSpec['specPrice'];
        } else {
            $price = ($isPintuan==1) ? model('common/Pintuans')->getPintuanPriceByTuanId($tuanId) : $shopPrice;
        }
        return $price;
    }



    //获取商品订单详情
    public function goodsOrder($userId, $goodsId, $specIds)
    {
        if (empty($userId) && empty($goodsId) && empty($specIds)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }

        $data = [];
        //商品内容以及合计
        $rs = DB::name('goods')->where(['goodsId'=>$goodsId,'goodsStatus'=>1])
            ->field('goodsName, marketPrice, shopPrice, goodsImg')
            ->find();
        $data['goods'] = $rs;
        $goodsSpec = model('common/Goods')->getGoodsSpec(['id'=>$specIds,'goodsId'=>$goodsId]);
        $specIdsStr = explode(':', $goodsSpec['specIds']);
        $specValue = $this->getSpecName($specIdsStr);
        $data['specValue'] = $specValue;
        return $data;
    }

    //获取运费
    public function getFreight($userId, $addressId, $goodsInfo)
    {
        if (empty($addressId) || empty($goodsInfo['shopId'])) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $freight = 0;
        if ($goodsInfo['isFreeShipping']==0) {
            $map['addressId'] = $addressId;
            $map['userId'] = $userId;
            $modelAddress = model('common/UserAddress');
            $addressInfo = $modelAddress->getAddressInfo($map);
            $tmp = explode('_',$addressInfo['areaIdPath']);
            $address['areaId2'] = $tmp[1];//记录配送城市
            $freight = WSTOrderFreight($goodsInfo['shopId'],$address['areaId2']);
        }
        return $freight;
    }

    public function getGoodsSpecValue($goodsId, $specIds)
    {
        $field = 'specPrice, marketPrice';
        $money = DB::name('goods_specs')
            ->where(['goodsId'=> $goodsId, 'specIds'=>$specIds,'dataFlag'=>1])
            ->field($field)
            ->find();
        return $money;
    }

    public function orderStatusNum($userId)
    {
        $return = [
            'waitPay' => 0,
            'waitPintuan' => 0,
            'waitDeliver' => 0,
            'delivering' => 0,
            'waitAppraise' => 0,
        ];
        $orderStatusNum = DB::name('orders')
            ->where('userId', $userId)
            ->where('dataFlag', 1)
            ->where('afterSaleStatus', '<>', 2)
            ->where('orderStatus in (-4,-2,0,1) or (orderStatus = 2 and isAppraise = 0)')
            //->where('(isPintuan = 0 and isPay = 1) or (isPintuan = 1 and isPay = 1) or (isPintuan = 0 and isPay = 0)')
            ->group('orderStatus')
            ->field('count(orderId) total,orderStatus')
            ->select();
        foreach ($orderStatusNum as $item) {
            if ($item['orderStatus'] == -4) {
                $return['waitPintuan'] = $item['total'];
            } elseif ($item['orderStatus'] == -2) {
                $return['waitPay'] = $item['total'];
            } elseif ($item['orderStatus'] == 0) {
                $return['waitDeliver'] = $item['total'];
            } elseif ($item['orderStatus'] == 1) {
                $return['delivering'] = $item['total'];
            } elseif ($item['orderStatus'] == 2) {
                $return['waitAppraise'] = $item['total'];
            }
        }
        return $return;
    }

    public function getList($userId, $orderStatus, $offset, $pageSize)
    {
        $orderModel = new O();
        $orderList = $orderModel->getList($userId, $offset, $pageSize, $orderStatus);
        foreach ($orderList['list'] as &$item) {
            $item['goodsImg'] = addImgDomain($item['goodsImg']);
            $item['orderStatus'] = $this->getOrderStatus($item['orderStatus'], $item['isAppraise']);
            $item['afterSaleStatus'] = $this->getAfterSaleStatus($item['afterSaleStatus']);
            $item['isPintuan'] = $item['orderStatus'] == 'waitPay' ? 0 : $item['isPintuan'];
            unset($item['isAppraise']);
            if ($item['isPintuan'] == 1) {
                $pintuanUserModel = new PU();
                $pintuanUser = $pintuanUserModel->getPintuanUserByOrderId($item['orderId']);
                $item['pintuan'] = (new P())->pintuanInfo($pintuanUser['tuanNo']);
            }
            unset($item['userId']);
        }
        unset($item);
        return $orderList;
    }

    public function getOrderStatus($orderStatus, $isAppraise)
    {
        if ($orderStatus == 2 && $isAppraise == 1) {
            $orderStatus = 3;
        }

        $orderStatusConfig = config('web.order_status');
        $orderStatusConfig = array_flip($orderStatusConfig);
        if (isset($orderStatusConfig[$orderStatus])) {
            return $orderStatusConfig[$orderStatus];
        }
        return $orderStatus;
    }

    public function getAfterSaleStatus($afterSaleStatus)
    {
        $afterSaleStatusConfig = config('web.after_sale_status');
        $afterSaleStatusConfig = array_flip($afterSaleStatusConfig);
        if (isset($afterSaleStatusConfig[$afterSaleStatus])) {
            return $afterSaleStatusConfig[$afterSaleStatus];
        }
        return $afterSaleStatus;
    }

    public function getRefundStatus($refundStatus)
    {
        $refundStatusConfig = config('web.refund_status');
        $refundStatusConfig = array_flip($refundStatusConfig);
        if (isset($refundStatusConfig[$refundStatus])) {
            return $refundStatusConfig[$refundStatus];
        }
        return $refundStatus;
    }

    public function getAfterSaleList($userId, $refundStatus, $offset, $pageSize)
    {
        $orderModel = new O();
        $afterSaleList = $orderModel->getAfterSaleList($userId, $offset, $pageSize, $refundStatus);
        foreach ($afterSaleList['list'] as &$item) {
            $item['goodsImg'] = addImgDomain($item['goodsImg']);
            $item['afterSaleStatus'] = $this->getAfterSaleStatus($item['afterSaleStatus']);
        }
        unset($item);
        return $afterSaleList;
    }

    protected function getPintuanUsers($order)
    {
        $pintuanUserModel = new PU();
        $pintuanUser = $pintuanUserModel->getPintuanUserByOrderId($order['orderId']);
        $pintuanUsers = $pintuanUserModel->getPintuanUserPhotoByTuanNo($pintuanUser->tuanNo);
        return $pintuanUsers;
    }

    public function filterGoodsSpec($specId)
    {
        $specName = '';
        if ($specId>0) {
            $goodsSpec = model('common/Goods')->getGoodsSpec(['id'=>$specId]);
            $specIdsStr = explode(':', $goodsSpec['specIds']);
            $specName = $this->getSpecName($specIdsStr);
        }
        return $specName;
    }

    public function getSpecName($arr)
    {
        if (!is_array($arr)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $str = '';
        foreach ($arr as $k=>$v) {
            $str .=$this->getSpecValue($v).' ';
        }
        return $str;
    }

    public function getSpecValue($speId)
    {
        $rs = DB::name('spec_items')
            ->where('itemId', $speId)
            ->value('itemName');
        return $rs;
    }

    /*
     * 订单支付状态
     */
    public function orderPayStatus($orderNo)
    {
        $orderWhere = [
            'orderNo'=>$orderNo,
        ];
        $ga = new GA();
        $orderInfo = model('common/Orders')->getOrder($orderWhere);
        $orderConfigStatus =config('web.order_status');
        $data['orderStatus'] =  $this->getOrderStatus($orderInfo->orderStatus, $orderInfo->isAppraise);
        $data['deadline'] = strtotime($orderInfo->createTime) + config('web.order_timeout');
        $data['time'] = time();
        //是否拼团
        if ($orderInfo->isPintuan==0) {
            $data['isPintuan'] = 0;
        } else {
            $p = new P();
            $pintuanInfo = $p->getPintuanInfoByOrderNo($orderNo);
            $data['isPintuan'] = 1;
            $data['pintuan'] = $pintuanInfo;
        }
        return WSTReturn('success', 1, $data);
    }

    public function payMoneyByZeroStatus($orderNo)
    {
        $co = new CO();
        $orderInfo = $co->getOrderInfoByOrderNo($orderNo);
        if ((time()-strtotime($orderInfo->createTime))>config('web.order_timeout')) throw AE::factory(AE::ORDER_TIMEOUT_ALREADY);
        if ($orderInfo->orderStatus!=-2) throw AE::factory(AE::ORDER_STATUS_NOT_EXISTS);
        if ($orderInfo->isPay==1) throw AE::factory(AE::ORDER_PAY_ALREADY);
        if ($orderInfo->realTotalMoney!=0) throw AE::factory(AE::ORDER_NOT_PAY);
        $orderStatus = 0;
        if ($orderInfo->isPintuan==1) {
            $orderStatus = -4;
        }
        $data = [
            'isPay'=>1,
            'orderStatus'=>$orderStatus,
            'payFrom' => 'platform',
            'payTime' => date('Y-m-d H:i:s'),
            'payType' => 1,
        ];
        $co->updateOrderData($orderInfo->orderId, $data);
        if ($orderInfo->isPintuan==1) {
            $pin = new P();
            $pin->afterPay($orderInfo->orderId);
        }
        return true;
    }
}