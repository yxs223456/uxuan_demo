<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/12
 * Time: 18:05
 */
namespace wstmart\common\service;

//use wstmart\common\helper\SwooleConnect;
use wstmart\common\model\GoodsActivityGoods;
use wstmart\common\model\Orders as O;
use wstmart\app\service\Users as ASUser;
use wstmart\common\model\Users as AMUser;
use wstmart\app\service\Orders as SO;
use wstmart\common\service\Pintuan as SP;
use wstmart\common\model\PintuanUsers as PU;
use wstmart\common\model\PintuanOrders as PO;
use wstmart\common\model\Pintuans as P;
use wstmart\common\model\Goods as G;
use wstmart\common\model\UserAddress as UA;
use wstmart\common\model\OrderRefunds as ORF;
use wstmart\common\model\WxPayOrders as WPO;
use wstmart\common\helper\Redis;
use wstmart\common\helper\WxTemplateNotify as WT;
use think\Db;
use wstmart\common\exception\AppException as AE;
use wstmart\app\model\Orders as M;
use wstmart\app\service\Orders as ASO;
use wstmart\common\service\WxTemplateNotify as WTN;
use wstmart\common\service\News;

class Orders
{
    public function fanliCallback($orderId)
    {
        $order = DB::name('orders')->alias('o')
            ->leftJoin('order_goods og', 'o.orderId=og.orderId')
            ->leftJoin('goods g', 'og.goodsId=g.goodsId')
            ->where('o.orderId', $orderId)
            ->field('o.orderNo,og.goodsId,g.goodsImg,g.goodsName,o.pid,o.createTime,o.milliCreateTime,
            o.realTotalMoney,og.goodsNum')
            ->find();
        if (empty($order)) {
            return;
        }

        //判断是否为返利活动商品
        $activityGoodsModel = new GoodsActivityGoods();
        $isFanliActivityGoods = $activityGoodsModel->checkGoodsInActivity($order['goodsId'], config('enum.goodsActivityType.fanli.value'));
        if ($isFanliActivityGoods == false) {
            return;
        }

        //返利用户购买返利商品后向返现回调通知
        $orderInfo = [
            'goodsId'=>$order['goodsId'],
            'goodsImg'=>$order['goodsImg'],
            'goodsName'=>$order['goodsName'],
            'pid'=>$order['pid'],
            'yxCreateTime'=>$order['createTime'],
            'createUnixtime'=>$order['milliCreateTime'],
        ];
        $fanliUrl = 'https://cash.365uxuan.com/wechat/yxOrderCallback';
//        if ($order['goodsNum'] == 1) {
            $orderInfo['orderNo'] = $order['orderNo'];
            $orderInfo['realTotalMoney'] = $order['realTotalMoney'];
            $result = fanliCurl($orderInfo, $fanliUrl);
//        } else {
//            $orderInfo['realTotalMoney'] = bcdiv($order['realTotalMoney'], $order['goodsNum'], 2);
//            for ($i=1; $i<=$order['goodsNum']; $i++) {
//                $orderInfo['orderNo'] = $order['orderNo'] . '_' . $i;
//                $result = fanliCurl($orderInfo, $fanliUrl);
//            }
//        }
    }

/*    public function fanliCallback($orderId)
    {
        $missionInfo = [
            'mission_code' => time() . $orderId . getRandomString(3),
            'param' => json_encode([
                'task_type' => 'fanliCallback',
                'order_id' => $orderId,
            ]),
            'end_time' => '',
            'mission_type' => config("enum.missionType.async.value"),
            'status' => config("enum.missionStatus.waiting.value"),
        ];
        (new SwooleConnect())->asyncTask($missionInfo['mission_code']);
    }


    protected function fanliCallbackFail($orderId, $orderInfo, $failTimes)
    {
        $timeout = $failTimes * 600;
        $missionInfo = [
            'mission_code' => time() . $orderId. getRandomString(3),
            'param' => json_encode([
                'task_type' => 'fanliCallbackFail',
                'order_info' => $orderInfo,
                'times' => $failTimes
            ]),
            'execute_time' => date('Y-m-d H:i:s', strtotime($orderInfo['yxCreateTime']) + $timeout),
            'mission_name' => '返利回调通知失败再次通知',
            'end_time' => '',
            'mission_type' => config('enum.missionType.setTimeout.value'),
            'status' => config('enum.missionStatus.waiting.value'),
        ];
        (new SwooleConnect)->setTimeoutTask($missionInfo["execute_time"], $missionInfo["mission_code"]);
    }*/

    public function cancel($orderId, $cancelReason)
    {
        $orderModel = new O();
        $order = $orderModel->getOrderById($orderId);

        $userId = ASUser::getUserByCache()['userId'];
        if ($userId != $order->userId) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        if ($order->orderStatus == -6) {
            throw AE::factory(AE::ORDER_TIMEOUT_ALREADY);
        }
        if ($order->orderStatus != -2) {
            throw AE::factory(AE::ORDER_PAY_ALREADY);
        }
        $orderTimeout = config('web.order_timeout');
        if (strtotime($order->createTime) <= time()-$orderTimeout) {
            throw AE::factory(AE::ORDER_TIMEOUT_ALREADY);
        }
        $this->doCancel($order, $cancelReason);
        return true;
    }

    protected function doCancel(\think\model $order, $cancelReason)
    {
        $pintuanUserModel = new PU();
        $pintuanOrderModel = new PO();
        $orderModel = new O();
        $orderGoods = $orderModel->getOrderGoodsByOrderId($order->orderId);

        $order->orderStatus = -1;
        $order->cancelReason = $cancelReason;
        if ($order->isPintuan == 1) {
            $pintuanUser = $pintuanUserModel->getPintuanUserByOrderId($order->orderId);
            $pintuanOrder = $pintuanOrderModel->getPintuanOrderByTuanNo($pintuanUser->tuanNo);
            $pintuanUser->tuanStatus = -2;
            $pintuanUser->dataFlag = 0;
            $pintuanOrder->saleNum -= 1;
            $pintuanOrder->needNum += 1;
        }
        Db::startTrans();
        try {
            Db::name('goods')->where('goodsId',$orderGoods['goodsId'])->setInc('goodsStock', $orderGoods['goodsNum']);
            Db::name('goods')->where('goodsId',$orderGoods['goodsId'])->setDec('saleNum', $orderGoods['goodsNum']);
            if ($orderGoods['goodsSpecId']) {
                Db::name('goods_specs')->where('id',$orderGoods['goodsSpecId'])->setInc('specStock', $orderGoods['goodsNum']);
                Db::name('goods_specs')->where('id',$orderGoods['goodsSpecId'])->setDec('saleNum', $orderGoods['goodsNum']);
            }
            $order->save();
            if ($order->isPintuan == 1) {
                $pintuanUser->save();
                $pintuanOrder->save();
            }
            if ($order->userCouponId>0) {
                $orderModel = new O();
                $orderModel->updateCouponsUseStatus($order->userCouponId, $order->userId, 0, $order->orderNo, '');
            }
            (new News())->cancelNotify($order);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function timeoutNotify(\think\model $order, $orderGoods)
    {
        if ($order->isPintuan) {
            return;
        }
        if ($order['orderSrc'] == 5) {//小程序模板通知
            (new WTN())->cancelOrderTimeout($order, $orderGoods);
        }
    }

    public function delete($orderId)
    {
        $orderModel = new O();
        $order = $orderModel->getOrderById($orderId);

        $userId = ASUser::getUserByCache()['userId'];
        if ($userId != $order->userId) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        if (!in_array($order->orderStatus, [-6, -5, -1]) &&
            !($order->orderStatus == 2 && $order->isAppraise == 1) &&
            !in_array($order->afterSaleStatus, [2, 3])) {
            throw AE::factory(AE::ORDER_DELETE_REFUSE);
        }
        $order->dataFlag = 0;
        $order->save();
        return true;
    }

    public function logisticsInfo($userId, $orderId)
    {
        $orderModel = new O();
        $order = $orderModel->getOrderById($orderId);
        if ($userId != $order->userId) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        if (!in_array($order['orderStatus'], [0,1,2])) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $orderInfo = $orderModel->orderInfo($orderId);
        $logistics = $this->getKuaidiStatus($orderInfo['expressCode'], $orderInfo['expressNo']);
        $temp = [];
        foreach ($logistics as $logistic) {
            $time = strtotime($logistic['time']);
            $date = date('Y-m-d', $time);
            $dateKey = strtotime($date);
            if (isset($temp[$dateKey])) {
                $temp[$dateKey]['list'][] = [
                    'time' => date('H:i:s', $time),
                    'context' => $logistic['context'],
                ];
            } else {
                $temp[$dateKey] = [
                    'date' => $date,
                    'list' => [[
                        'time' => date('H:i:s', $time),
                        'context' => $logistic['context'],
                    ]],
                ];
            }
        }
        krsort ($temp);
        $temp = array_values($temp);
        return [
            'expressName' => $orderInfo['expressName'],
            'expressNo' => $orderInfo['expressNo'],
            'expressPhone' => $orderInfo['expressPhone'],
            'expressData' => $temp,
            'extra' => [
                'goodsImg' => addImgDomain($orderInfo['goodsImg']),
            ],
        ];
    }

    public function info($userId, $orderId)
    {
        $orderModel = new O();
        $order = $orderModel->getOrderById($orderId);
        if ($userId != $order->userId) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }

        $orderInfo = $orderModel->orderInfo($orderId);
        $infoData = $this->orderPublicInfo($orderInfo);
        if ($orderInfo['orderStatus'] == -2) {
            $this->orderWaitPayInfo($infoData, $orderInfo);
        } elseif ($orderInfo['orderStatus'] == -4) {
            $this->orderWaitPintuanInfo($infoData, $orderInfo);
        } elseif ($orderInfo['orderStatus'] == 0) {
            $this->orderWaitDeliverInfo($infoData, $orderInfo);
        } elseif ($orderInfo['orderStatus'] == 1) {
            $this->orderDeliveringInfo($infoData, $orderInfo);
        } elseif ($orderInfo['orderStatus'] == 2 &&
            ($orderInfo['afterSaleStatus'] == 0 || $orderInfo['afterSaleStatus'] == 5)) {
            $this->orderAfterDeliverInfo($infoData, $orderInfo);
        }

        return $infoData;
    }

    protected function orderPublicInfo($orderInfo)
    {
        $publicInfo['status'] = [
            'orderStatus' => (new SO())->getOrderStatus($orderInfo['orderStatus'], $orderInfo['isAppraise']),
        ];
        $userAddressModel = new UA();
        list($province, $city, $county, $address) = explode(' ', $orderInfo['userAddress']);
        $publicInfo['address'] = [
            'nickname' => $orderInfo['userName'],
            'userPhone' => $orderInfo['userPhone'],
            'userAddress' => $orderInfo['userAddress'],
            'isDefaultAddress' => $userAddressModel->checkAddressIsDefault($orderInfo['userId'], $province, $city, $county, $address, $orderInfo['userPhone']),
        ];
        $publicInfo['goods'] = [
            'goodsImg' => addImgDomain($orderInfo['goodsImg']),
            'goodsName' => $orderInfo['goodsName'],
            'littleDesc' => $orderInfo['littleDesc'],
            'goodsSpecNames' => $orderInfo['goodsSpecNames'],
            'goodsNum' => $orderInfo['goodsNum'],
            'goodsPrice' => $orderInfo['goodsPrice'],
            'goodsId' => $orderInfo['goodsId'],
        ];
        $publicInfo['order'] = [
            'orderId' =>$orderInfo['orderId'],
            'noticeDeliver' => $orderInfo['noticeDeliver'],
            'orderNo' => $orderInfo['orderNo'],
            'submitTime' => $orderInfo['createTime']
        ];
        $publicInfo['pay'] = [
            'payFrom' => $orderInfo['payFrom'] == 'weixinpays' ? '微信' : '',
            'goodsMoney' => $orderInfo['goodsMoney'],
            'deliverMoney' => $orderInfo['deliverMoney'],
            'couponsValue' => $orderInfo['couponsValue'],
            'realTotalMoney' => $orderInfo['realTotalMoney'],
        ];
        $publicInfo['afterSaleStatus'] = (new SO())->getAfterSaleStatus($orderInfo['afterSaleStatus']);
        $publicInfo['refundId'] = $orderInfo['refundId'];
        $orderInfo['isPintuan'] = $orderInfo['orderStatus'] == -2 ? 0 : $orderInfo['isPintuan'];
        $publicInfo['isPintuan'] = $orderInfo['isPintuan'];
        if ($orderInfo['isPintuan'] == 1) {
            $pintuanUserModel = new PU();
            $pintuanUser = $pintuanUserModel->getPintuanUserByOrderId($orderInfo['orderId']);
            $pintuanModel = new P();
            $pintuanOrder = $pintuanModel->getPintuanOrderByTuanNo($pintuanUser['tuanNo']);
            $publicInfo['pintuan'] = [
                'tuanNo' => $pintuanOrder['tuanNo'],
                'tuanNum' => $pintuanOrder['tuanNum'],
                'saleNum' => $pintuanOrder['saleNum'],
                'needNum' => $pintuanOrder['needNum'],
                'users' => $pintuanUserModel->getPintuanUserPhotoByTuanNo($pintuanUser['tuanNo']),
            ];
        }
        return $publicInfo;
    }

    protected function orderWaitPayInfo(&$infoData, $orderInfo)
    {
        $infoData['status']['time'] = time();
        $infoData['status']['deadline'] = strtotime($orderInfo['createTime']) + config('web.order_timeout');
    }

    protected function orderWaitPintuanInfo(&$infoData, $orderInfo)
    {
        $pintuanUserModel = new PU();
        $pintuanUser = $pintuanUserModel->getPintuanUserByOrderId($orderInfo['orderId']);
        $pintuanModel = new P();
        $pintuanOrder = $pintuanModel->getPintuanOrderByTuanNo($pintuanUser['tuanNo']);

        $infoData['status']['time'] = time();
        $infoData['status']['deadline'] = $pintuanOrder['createdAt'] + $pintuanOrder['tuanTime'];

        $infoData['order']['payTime'] = $orderInfo['payTime'];
    }

    protected function orderWaitDeliverInfo(&$infoData, $orderInfo)
    {
        if ($orderInfo['isPintuan'] == 1) {
            $pintuanUserModel = new PU();
            $pintuanUser = $pintuanUserModel->getPintuanUserByOrderId($orderInfo['orderId']);
            $pintuanModel = new P();
            $pintuanOrder = $pintuanModel->getPintuanOrderByTuanNo($pintuanUser['tuanNo']);
            $infoData['order']['pintuanSuccessTime'] = $pintuanOrder['successTime'];
        }
        $infoData['order']['payTime'] = $orderInfo['payTime'];
    }

    protected function orderDeliveringInfo(&$infoData, $orderInfo)
    {
        $infoData['status']['time'] = time();
        $infoData['status']['deadline'] = strtotime($orderInfo['deliveryTime']) + config('web.order_receive_time');
        if ($orderInfo['isPintuan'] == 1) {
            $pintuanUserModel = new PU();
            $pintuanUser = $pintuanUserModel->getPintuanUserByOrderId($orderInfo['orderId']);
            $pintuanModel = new P();
            $pintuanOrder = $pintuanModel->getPintuanOrderByTuanNo($pintuanUser['tuanNo']);
            $infoData['order']['pintuanSuccessTime'] = $pintuanOrder['successTime'];
        }
        $infoData['order']['payTime'] = $orderInfo['payTime'];
        $infoData['order']['deliveryTime'] = $orderInfo['deliveryTime'];
        $infoData['order']['expressName'] = $orderInfo['expressName'];
        $infoData['order']['expressNo'] = $orderInfo['expressNo'];

        $logistics = $this->getKuaidiStatus($orderInfo['expressCode'], $orderInfo['expressNo']);
        if (empty($logistics)) {
            $infoData['logistics']['time'] = date('Y-m-d H:i:s');
            $infoData['logistics']['context'] = '暂无物流信息';
        } else {
            $infoData['logistics']['time'] = $logistics[0]['time'];
            $infoData['logistics']['context'] = $logistics[0]['context'];
        }
    }

    protected function orderAfterDeliverInfo(&$infoData, $orderInfo)
    {
        if ($orderInfo['isPintuan'] == 1) {
            $pintuanUserModel = new PU();
            $pintuanUser = $pintuanUserModel->getPintuanUserByOrderId($orderInfo['orderId']);
            $pintuanModel = new P();
            $pintuanOrder = $pintuanModel->getPintuanOrderByTuanNo($pintuanUser['tuanNo']);
            $infoData['order']['pintuanSuccessTime'] = $pintuanOrder['successTime'];
        }
        $infoData['order']['payTime'] = $orderInfo['payTime'];
        $infoData['order']['deliveryTime'] = $orderInfo['deliveryTime'];
        $infoData['order']['expressName'] = $orderInfo['expressName'];
        $infoData['order']['expressNo'] = $orderInfo['expressNo'];

        $logistics = $this->getKuaidiStatus($orderInfo['expressCode'], $orderInfo['expressNo']);
        if (empty($logistics)) {
            $infoData['logistics']['time'] = date('Y-m-d H:i:s');
            $infoData['logistics']['context'] = '暂无物流信息';
        } else {
            $infoData['logistics']['time'] = $logistics[0]['time'];
            $infoData['logistics']['context'] = $logistics[0]['context'];
        }
    }

    /**
     * 查询快递信息
     */
    public function getKuaidiStatus($expressCode, $expressNo)
    {
        $redis = new Redis();
        $kuaidiCacheKey = $expressCode. '_' . $expressNo;
        $kuaidi = json_decode($redis->get($kuaidiCacheKey), true);
        if (empty($kuaidi) || (($kuaidi['queryTime'] <= (time() - (86400 * 2))) && $kuaidi['state'] != 3)) {
            $kuaidi = $this->getKuaidi100($expressCode, $expressNo);
            $redis->set($kuaidiCacheKey, json_encode($kuaidi, JSON_UNESCAPED_UNICODE));
        }
        $kuaidi = isset($kuaidi['data']) ? $kuaidi['data'] : [];
        return $kuaidi;
    }

    protected function getKuaidi100($expressCode, $expressNo)
    {
        $kuaidi100Key = config('kuaidi.kuaidi100.key');
        $companys = array('ems','shentong','yuantong','shunfeng','yunda','tiantian','zhongtong','zengyisudi');
        if(in_array($expressCode,$companys)){
            $url = 'http://www.kuaidi100.com/query?type=' . $expressCode . '&postid=' . $expressNo;
        }else{
            $url = 'http://api.kuaidi100.com/api?id='.$kuaidi100Key.'&com='.$expressCode.'&nu='.$expressNo.'&show=0&muti=1&order=desc';
        }
        $data = curl($url, 'get', null, false, true);
        if (isset($data['status']) && ($data['status'] == 1 || $data['status'] == 200)) {
            $data['queryTime'] = time();
        } else {
            $data = null;
        }
        return $data;
    }

    public function noticeDeliver($orderId)
    {
        $orderModel = new O();
        $order = $orderModel->getOrderById($orderId);

        $userId = ASUser::getUserByCache()['userId'];
        if ($userId != $order->userId) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }

        if ($order->noticeDeliver == 1) {
            throw AE::factory(AE::ORDER_NOTICE_DELIVER_ALREADY);
        }
        $order->noticeDeliver = 1;
        $order->save();
        return true;
    }

    /*
     * 确认收货
     */
    public function confirmCollectGoods($orderId)
    {
        $orderModel = new O();
        $orderInfo = $orderModel->getOrderById($orderId);
        $userId = ASUser::getUserByCache()['userId'];
        if ($userId != $orderInfo->userId) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        if ($orderInfo->orderStatus==2)  return true;
        if ($orderInfo->afterSaleStatus==2 || $orderInfo->orderStatus!=1) throw AE::factory(AE::REFUND_ORDER_NOT_COLLECT);
        $data = [
            'orderStatus'=>2,
            'receiveTime'=>date('Y-m-d H:i:s'),
        ];
        $orderModel->updateOrderData($orderId, $data);
        return true;
    }

    /**
     * 售后退款，外层需数据库事务支持
     * @param $id order_refunds表id
     */
    public function dealOrderRefund($id)
    {
        $orderRefundModel = (new ORF())->getOrderRefundModelById($id);
        $orderModel = (new O())->getOrderById($orderRefundModel->orderId);
        if ($orderModel->isRefund == 1) {
            throw AE::factory(AE::ORDER_REFUND_SECCESS);
        }
        if ($orderModel->payFrom === 'weixinpays') {
            $weixinPayOrderModel = (new WPO())->getWxPayOrderModel(['type'=>'orderPay','targetId'=>$orderModel->orderId,'transaction_id'=>$orderModel['tradeNo']]);
            $wxRefundInfo = [
                'appid' => $weixinPayOrderModel->appid,
                'transaction_id' => $weixinPayOrderModel->transaction_id,
                'out_trade_no' => $weixinPayOrderModel->out_trade_no,
                'out_refund_no' => date('YmdHis') . $orderModel->orderId . getRand(6),
                'total_fee' => $weixinPayOrderModel->total_fee,
                'refund_fee' => $orderRefundModel->backMoney * 100,
                'refund_desc' => '每日优选退款',
                'type' => 'afterSaleRefund',
                'targetId' => $orderRefundModel->id,
            ];
            model('common/Payments')->insertWechatRefundData($wxRefundInfo);
            $weixinPayOrderModel->isRefund = 1;
            $weixinPayOrderModel->save();
        }
        $orderModel->isRefund = 1;
        $orderModel->save();
        if ($orderModel->isPintuan == 1) {
            (new SP())->afterAfterSaleRefund($orderModel->orderId);
        }
    }

    public function afterSaleAuditNotify(\think\model $order, \think\model $orderRefund)
    {
        if ($order->payFrom === 'weixinpays') {
            $weixinPayOrder = (new WPO())->getWxPayOrderModel(['type'=>'orderPay','targetId'=>$order->orderId,'transaction_id'=>$order['tradeNo']]);
            if ($weixinPayOrder->wechatType === 'miniPrograms') {
                $wxTemplateNotify = new WTN();
                $wxTemplateNotify->afterSaleAudit($order, $orderRefund, $weixinPayOrder);
            }
        }
    }

    public function afterSaleGoodsAuditNotify(\think\model $order, \think\model $orderRefund)
    {
        if ($order->payFrom === 'weixinpays') {
            $weixinPayOrder = (new WPO())->getWxPayOrderModel(['type'=>'orderPay','targetId'=>$order->orderId,'transaction_id'=>$order['tradeNo']]);
            if ($weixinPayOrder->wechatType === 'miniPrograms') {
                $wxTemplateNotify = new WTN();
                $wxTemplateNotify->afterSaleGoodsAudit($order, $orderRefund, $weixinPayOrder);
            }
        }
    }

    public function quickSubmitOrder($userId, $goodsId, $couponId, $goodsNum, $specIds, $addressId, $isPintuan, $orderSrc, $tuanId, $tuanNo, $pid)
    {
        $m = new M();
        $aso = new ASO();
        $g = new G();
        $deliverType = 0;
        $goodsShopInfo = model('common/Goods')->getGoodsShopInfo($goodsId);
        //是否拼团------------------需要计算拼团的价格（没规格应该取那个值）
        $ua = new UA();
        if ($specIds>0) {
            $specStock = $g->getGoodsSpec(['id'=>$specIds], 'specStock');
            if ($specStock['specStock'] < $goodsNum) throw AE::factory(AE::GOODS_STOCK_LITTLE);
        }
        if ($goodsShopInfo['goodsStock']< $goodsNum) throw AE::factory(AE::GOODS_STOCK_LITTLE);

        $goodsMoney = $aso->getGoodsPrice($goodsId, $specIds, $isPintuan, $tuanId, $goodsShopInfo['shopPrice']);
        $addressInfo = $ua->getAddressInfo(['addressId' => $addressId, 'userId'=>$userId]);
        $deliverMoney = $addressId>0 ? $aso->getFreight($userId, $addressId,$goodsShopInfo) : 0;
        $couponsValue = $aso->getCouponValue($userId, $couponId, bcmul($goodsMoney,$goodsNum,2), $goodsId);
        $realTotalMoney = bcsub(bcadd(bcmul($goodsMoney,$goodsNum,2),$deliverMoney,2),$couponsValue,2);
        if ($realTotalMoney < 0) $realTotalMoney = 0;
        $data = [
            'goodsId' => $goodsId, 'goodsNum' => $goodsNum, 'goodsMoney' => $goodsMoney,
            'deliverMoney' => $deliverMoney, 'deliverType' => $deliverType,
            'orderSrc' => $orderSrc, 'couponValue' => $couponsValue, 'couponsId' => $couponId, 'realTotalMoney' => $realTotalMoney,
            'specId' => $specIds, 'isPintuan' => $isPintuan,
        ];
        if ($pid === '') {
            $user = (new AMUser())->getUserById($userId);
            if (!empty($user->wxUnionId)) {
                $fanliInfo = Db::name('fanli_shopkeepers')->where('unionId', $user->wxUnionId)->find();
                if ($fanliInfo) {
                    $pid = $fanliInfo['pid'];
                } else {
                    $pid = $user->pid;
                }
            } else {
                $pid = $user->pid;
            }
        }
        $rs = $m->quickSubmitOrder($userId, $goodsId, $couponId, $goodsNum, $data, $addressInfo, $goodsShopInfo, $isPintuan, $tuanId, $tuanNo, $pid);
        //$this->sendWxTemplate($userId,$data,$rs,$goodsShopInfo);
        return $rs;
    }

    public function sendWxTemplate($userId,$data,$rs,$goodsShopInfo)
    {
        $userInfo = (new AMUser())->getUserById($userId);
        if ($userInfo->subscribeStatus!=1) return true;
        $type = 'order_submit';
        $templateInfo = config('weixin.template.wx'.$type);
        $wt = new WT();
        $templateData['userId'] = $userId;
        $templateData['desc'] = $templateInfo['des'];
        $templateData['targetId'] = $rs['orderId'];
        $templateData['wxClient'] = $data['orderSrc'];
        $templateData['appid'] = config('weixin.publicNumber.appid');
        $msg = [
            'first'=>['value'=>$templateInfo['des']],
            'keyword1'=>['value'=>$goodsShopInfo['goodsName']],
            'keyword2'=>['value'=>$rs['orderNo']],
        ];
        $templateData['params'] = json_encode($wt->filterWeixinTemplate($msg,$userInfo->wxOpenId,null,$templateInfo['template_id'],null,null));
        $this->insertVersionData($templateData);
    }

    public function insertVersionData($data)
    {
        $insert = Db::name('template_notify')->insert($data);
        return $insert;
    }

}