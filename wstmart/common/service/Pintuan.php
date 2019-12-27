<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/6
 * Time: 16:09
 */
namespace wstmart\common\service;

use wstmart\common\model\Pintuans as P;
use wstmart\common\model\PintuanOrders as PO;
use wstmart\common\model\PintuanUsers as PU;
use wstmart\common\model\Orders as O;
use think\Db;
use wstmart\common\exception\AppException as AE;
use wstmart\common\service\WxTemplateNotify as WTN;
use wstmart\common\model\WxPayOrders as WPO;
use wstmart\common\model\Users as U;
use wstmart\common\service\TaskWelfare as CSTW;
use wstmart\common\model\Goods as G;
use wstmart\common\service\News;

class Pintuan
{
    /**
     * 确认订单后处理拼团,外层需数据库事务支持
     * @param $orderId int 订单id
     * @param $orderId tuanId 商品拼团配置id
     * @param null $tuanNo 拼团团号,参与拼团时需要提供
     * @throws AE
     */
    public function afterCreateOrder($orderId, $tuanId, $tuanNo = null)
    {
        $orderModel = new O();
        $order = $orderModel->getOrderById($orderId);
        $orderGoods = $orderModel->getOrderGoodsByOrderId($orderId);
        $pintuanModel = new P();
        $pintuan = $pintuanModel->getPintuanByTuanId($tuanId);
        if ($pintuan->goodsId != $orderGoods['goodsId']) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $isNewTuan = false;
        if (empty($tuanNo)) {
            if (($pintuan->tuanSum-$pintuan->tuanSaleNum)<=0) throw AE::factory(AE::ORDER_PINTUAN_NUMBER_NOT_ENOUGH);
            $tuanNo = $this->createPintuan($pintuan, $order->userId);
            Db::name('pintuans')->where('tuanId', $tuanId)->setInc('tuanSaleNum', 1);
            $isNewTuan = true;
        }
        $tuanOrder = $pintuanModel->getPintuanOrderByTuanNo($tuanNo);
        if ($isNewTuan) {
            (new CSTW())->pintaunOriginator($order->userId, $tuanOrder['id'], $orderGoods['goodsId']);
        }
        if ($tuanOrder['tuanId'] != $tuanId) {
            throw AE::factory(AE::ORDER_TUAN_ERR);
        }
        $pintuanUserModel = new PU();
        $pintuanUser = $pintuanUserModel->getPintuanUserByTuanNoAndUserId($tuanNo, $order->userId);
        if (!empty($pintuanUser)) {
            throw AE::factory(AE::ORDER_PINTUAN_JOIN_ALREADY);
        }
        if ($tuanOrder['needNum'] <= 0) {
            throw AE::factory(AE::ORDER_PINTUAN_NUMBER_NOT_ENOUGH);
        }
        if ($orderGoods['goodsNum'] > $pintuan->goodsNum) {
            throw AE::factory(AE::ORDER_PINTUAN_NUM_OVERRUN);
        }
        if (time() >= $tuanOrder['createdAt'] + $tuanOrder['tuanTime']) {
            throw AE::factory(AE::ORDER_PINTUAN_OVER);
        }
        $this->updatePintuanNum($tuanNo);
        $this->createPintuanUser($tuanOrder, $order, $orderGoods);
        return $tuanNo;
    }

    protected function createPintuan(\think\model $tuan, $headUserId)
    {
        $tuanNo = tuanNo($tuan->tuanId, $headUserId);
        $data = [
            'tuanId' => $tuan->tuanId,
            'goodsId' => $tuan->goodsId,
            'tuanNo' => $tuanNo,
            'tuanNum' => $tuan->tuanNum,
            'needNum' => $tuan->tuanNum,
            'tuanTime' => $tuan->tuanTime * 3600,
            'headUserId' => $headUserId,
            'createdAt' => time(),
        ];
        DB::name('pintuan_orders')->data($data)->insert();
        return $tuanNo;
    }

    protected function updatePintuanNum($tuanNo)
    {
        DB::name('pintuan_orders')
            ->where('tuanNo', $tuanNo)
            ->inc('saleNum', 1)
            ->dec('needNum', 1)
            ->update();
    }

    protected function createPintuanUser($pintuanOrder, \think\model $order, $orderGoods)
    {
        $data = [
            'userId' => $order['userId'],
            'tuanId' => $pintuanOrder['tuanId'],
            'shopId' => $order['shopId'],
            'orderId' => $order['orderId'],
            'orderNo' => $order['orderNo'],
            'goodsId' => $orderGoods['goodsId'],
            'tuanNo' => $pintuanOrder['tuanNo'],
            'goodsNum' => $orderGoods['goodsNum'],
            'tuanStatus' => 0,
            'isHead' => $order['userId'] == $pintuanOrder['headUserId'] ? 1 : 0,
            'isPay' => 0,
            'createdAt' => time(),
        ];
        DB::name('pintuan_users')->data($data)->insert();
    }

    /**
     * 支付订单后处理拼团,外层需数据库事务支持
     * @param $orderId int 订单id
     */
    public function afterPay($orderId)
    {
        $pintuanUserModel = new PU();
        $pintuanUser = $pintuanUserModel->getPintuanUserByOrderId($orderId);
        $tuanOrder = (new PO())->getPintuanOrderByTuanNo($pintuanUser['tuanNo']);
        $pintuanUser->isPay = 1;
        $pintuanUser->tuanStatus = 1;
        $pintuanUser->save();
        $this->pintuanIsSuccess($tuanOrder);
    }

    public function pintuanIsSuccess(\think\model $tuanOrder)
    {
        $payOrder = DB::name('pintuan_users')
            ->where('tuanNo', $tuanOrder['tuanNo'])
            ->where('tuanStatus', 1)
            ->where('isPay', 1)
            ->where('refundStatus', 0);
        $payOrder1 = clone $payOrder;
        $payCount = $payOrder1->count();
        if ($payCount < $tuanOrder['tuanNum']) {
            return false;
        } else {
            $tuanOrder->tuanStatus = 1;
            $tuanOrder->successTime = date('Y-m-d H:i:s');
            $tuanOrder->save();
            $pintuanUsers = $payOrder->select();
            $this->afterSuccess($pintuanUsers, $tuanOrder);
            return true;
        }
    }

    protected function afterSuccess(array $pintuanUsers, $tuanOrder)
    {
        foreach ($pintuanUsers as $pintuanUser) {
            DB::name('pintuan_users')
                ->where('id', $pintuanUser['id'])
                ->update(['tuanStatus' => 2]);
            DB::name('orders')
                ->where('orderId', $pintuanUser['orderId'])
                ->update(['orderStatus'=>0]);
        }
        $this->successNotify($pintuanUsers, $tuanOrder);
    }

    protected function successNotify($pintuanUsers, $tuanOrder)
    {
        $pintuanUserIds = array_column($pintuanUsers, 'userId');
        $pintuanUsersInfo = DB::name('users')
            ->whereIn('userId', $pintuanUserIds)
            ->field('userId,nickname,userPhone,mpOpenId')
            ->select();
        $usersNickname = [];
        foreach ($pintuanUsersInfo as $item) {
            $usersNickname[] = $item['nickname'] ? $item['nickname'] : hideUserPhone($item['userPhone']);
        }
        $goods = (new G())->getGoodsById($tuanOrder['goodsId']);
        $goodsName = $goods['goodsName'];
        $goods['tuanNum'] = $tuanOrder['tuanNum'];
        $wtn = new WTN();
        $news = new News();
        $pintuanUsersMpOpen = array_column($pintuanUsersInfo, 'mpOpenId', 'userId');
        foreach ($pintuanUsers as $pintuanUser) {
            $order = (new O())->getOrderById($pintuanUser['orderId']);
            if ($order->payFrom === 'weixinpays') {
                $weixinPayOrder = (new WPO())->getWxPayOrderModel(['type'=>'orderPay','targetId'=>$order->orderId,'transaction_id'=>$order['tradeNo']]);
                if ($weixinPayOrder->wechatType === 'miniPrograms') {
                    $wtn->pintuanSuccessNotify($pintuanUser, $goodsName, $usersNickname, $weixinPayOrder,
                        $tuanOrder['id'], $pintuanUsersMpOpen[$pintuanUser['userId']]);
                }
            }
            $news->pintuanSuccess($order->orderId,$order->orderNo,$pintuanUser['userId'],$goods,$usersNickname);
        }
    }

    public function pintuanInfo($tuanNo)
    {
        $pintuanModel = new P();
        $pintuanOrder = $pintuanModel->getPintuanOrderByTuanNo($tuanNo);
        $pintuanUserModel = new PU();
        $pintuanUsers = $pintuanUserModel->getPintuanUserPhotoByTuanNo($tuanNo);
        $pintuanHead = $pintuanUserModel->getHeadUserByTuanNo($tuanNo);
        return [
            'tuanNo' => $tuanNo,
            'tuanNum' => $pintuanOrder['tuanNum'],
            'saleNum' => $pintuanOrder['saleNum'],
            'needNum' => $pintuanOrder['needNum'],
            'time' => time(),
            'tuanStatus' => $pintuanHead['isPay'] != 1 ? '-2' : (($pintuanOrder['tuanStatus'] == 0 && $pintuanOrder['needNum'] != 0) ?
                (time() >= $pintuanOrder['createdAt'] + $pintuanOrder['tuanTime'] ? -1 : 0) :
                $pintuanOrder['tuanStatus']),
            'deadline' => $pintuanOrder['createdAt'] + $pintuanOrder['tuanTime'],
            'users' => $pintuanUsers,
        ];
    }

    public function getPintuanInfoByOrderNo($orderNo)
    {
        $pintuanUserModel = new PU();
        $pintuanUser = $pintuanUserModel->getPintuanUserByOrderNo($orderNo);
        $pintuanModel = new P();
        $pintuanOrder = $pintuanModel->getPintuanOrderByTuanNo($pintuanUser['tuanNo']);
        $pintuanHead = $pintuanUserModel->getHeadUserByTuanNo($pintuanUser['tuanNo']);
        $info = [
            'tuanNo' => $pintuanOrder['tuanNo'],
            'tuanNum' => $pintuanOrder['tuanNum'],
            'saleNum' => $pintuanOrder['saleNum'],
            'needNum' => $pintuanOrder['needNum'],
            'time' => time(),
            'tuanStatus' => $pintuanHead['isPay'] != 1 ? '-2' : (($pintuanOrder['tuanStatus'] == 0 && $pintuanOrder['needNum'] != 0) ?
                (time() >= $pintuanOrder['createdAt'] + $pintuanOrder['tuanTime'] ? -1 : 0) :
                $pintuanOrder['tuanStatus']),
            'deadline' => $pintuanOrder['createdAt'] + $pintuanOrder['tuanTime'],
            'users' => $pintuanUserModel->getPintuanUserPhotoByTuanNo($pintuanUser['tuanNo']),
        ];
        return $info;
    }

    public function sharePintuan($orderNo, $userId)
    {
        $pintuanUserModel = new PU();
        $pintuanUser = $pintuanUserModel->getPintuanUserByOrderNo($orderNo);
        $pintuanModel = new P();
        $pintuanOrder = $pintuanModel->getPintuanOrderByTuanNo($pintuanUser->tuanNo);
        $goodsModel = new G();
        $goods = $goodsModel->getGoodsById($pintuanOrder['goodsId']);
        $appName = config('web.app_name');
        if (!empty($userId)) {
            $user = (new U())->getUserById($userId);
            $sharer = $user['nickname'];
        }
        if (empty($sharer)) {
            $sharer = $appName;
        }
        return [
            'title' => "【好友力荐】{$sharer}邀您拼团购买{$goods->goodsName}",
            'content' => '在' . $appName .'，和好友一起优选今日生活！',
            'icon' => addImgDomain($goods->goodsImg),
            'link' => config('web.h5_url') . "/tuan_detail?goodsId={$goods->goodsId}&tuanNo={$pintuanUser->tuanNo}",
        ];
    }

    /**
     * 拼团失败退款,先处理退款成功状态，然后退款。退款在脚本处理
     */
    public function failRefund($pintuanUser)
    {
        $orderModel = new O();
        $order = $orderModel->getOrderById($pintuanUser['orderId']);
        if ($order['isRefund'] == 1) {//已退款不予处理
            return;
        }
        Db::startTrans();
        try {
            if ($order['payFrom'] === 'weixinpays') {
                $wxPay = DB::name('wx_pay_orders')
                    ->where('type', 'orderPay')
                    ->where('targetId', $order['orderId'])
                    ->where('transaction_id', $order['tradeNo'])
                    ->where('isPay', 1)
                    ->find();
                if (empty($wxPay) || $wxPay['isRefund'] == 1) {//没有对应付款记录或已退款不予处理
                    return;
                }
                $wxRefund = DB::name('wx_refund_orders')
                    ->where('type', 'pintanTimeout')
                    ->where('targetId',$order['orderId'])
                    ->find();
                if (!empty($wxRefund)) {//已经有退款记录不予处理
                    return;
                }
                $wxRefundInfo = [
                    'appid' => $wxPay['appid'],
                    'transaction_id' => $wxPay['transaction_id'],
                    'out_trade_no' => $wxPay['out_trade_no'],
                    'out_refund_no' => date('YmdHis') . $order['orderId'] . getRand(6),
                    'total_fee' => $wxPay['total_fee'],
                    'refund_fee' => $wxPay['total_fee'],
                    'refund_desc' => '拼团失败',
                    'type' => 'pintanTimeout',
                    'targetId' => $order['orderId'],
                ];
                model('common/Payments')->insertWechatRefundData($wxRefundInfo);
                DB::name('wx_pay_orders')
                    ->where('id', $wxPay['id'])
                    ->update(['isRefund'=>1]);
            }
            $this->refundSuccessCallback($pintuanUser['id'], $order['orderId']);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function refundSuccessCallback($pintuanUserId, $orderId)
    {
        $orderModel = new O();
        $order = $orderModel->getOrderById($orderId);
        $order->isRefund = 1;
        $order->orderStatus = -5;
        $order->save();
        DB::name('pintuan_users')
            ->where('id', $pintuanUserId)
            ->update(['tuanStatus'=>-1,'refundStatus'=>1,'refundTime'=>date('Y-m-d H:i:s')]);
        if ($order->userCouponId>0) {
            $orderModel->updateCouponsUseStatus($order->userCouponId, $order->userId, 0, '', '');
        }
        $orderGoods = $orderModel->getOrderGoodsByOrderId($orderId);
        Db::name('goods')->where('goodsId',$orderGoods['goodsId'])->setInc('goodsStock', $orderGoods['goodsNum']);
        Db::name('goods')->where('goodsId',$orderGoods['goodsId'])->setDec('saleNum', $orderGoods['goodsNum']);
        if ($orderGoods['goodsSpecId']) {
            Db::name('goods_specs')->where('id',$orderGoods['goodsSpecId'])->setInc('specStock', $orderGoods['goodsNum']);
            Db::name('goods_specs')->where('id',$orderGoods['goodsSpecId'])->setDec('saleNum', $orderGoods['goodsNum']);
        }
    }

    /**
     * 售后退款后将参团修改为已退款状态,外层需数据库事务支持
     * @param $orderId int 订单id
     */
    public function afterAfterSaleRefund($orderId)
    {
        $pintuanUserModel = new PU();
        $pintuanUser = $pintuanUserModel->getPintuanUserByOrderId($orderId);
        $pintuanUser->refundStatus = 1;
        $pintuanUser->refundTime = date('Y-m-d H:i:s');
        $pintuanUser->save();
    }
}