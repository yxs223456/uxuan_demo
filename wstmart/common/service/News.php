<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/10/16
 * Time: 16:33
 */

namespace wstmart\common\service;

use wstmart\common\model\UserNews;
use wstmart\common\service\WxTemplateNotify as WTN;
use wstmart\common\model\Users as AMUser;
use wstmart\common\model\Orders as O;
use wstmart\common\model\OrderRefunds as ORF;
use wstmart\common\model\Pintuans;
use wstmart\common\model\Express;
use wstmart\common\model\Coupons;
use wstmart\app\service\Users as ASUer;

class News
{

    /*
     * 订单取消通知
     */
    public function cancelNotify(\think\model $order)
    {
        $noticType = 'orderCancel';
        $desc = '您的的订单因30分钟内未支付，已自动取消';
        $orderGoodsInfo = (new O())->getOrderGoodsByOrderId($order->orderId);
        if (!$orderGoodsInfo) return true;
        $url = null;
        $wtn = new WTN();
        $u = new AMUser();
        $remark = '查看商品，再次下单';
        $userInfo = $u->getFieldsById($order->userId, 'userId, userPhone, nickname, userName, wxOpenId, isReceiveNews, receiveTime');
        if (!$userInfo) return true;
        if (!empty($userInfo->wxOpenId)){
            $msg['first'] = $desc;
            $msg['orderProductPrice'] = $order->realTotalMoney.'元';
            $msg['orderProductName'] = $orderGoodsInfo['goodsName'];
            $msg['orderAddress'] = $order->userAddress;
            $msg['orderName'] = $order->orderNo;
            $path = config('weixin.template.wx.order_cancel.path') . $orderGoodsInfo['goodsId'];
            $wtn->sendWxTemplate($msg,'order_cancel',$userInfo->wxOpenId, $remark, $url, true, $path);
        }
        $title = '订单取消通知';
        $data['orderMoney'] = $order->realTotalMoney.'元';
        $data['goodsName'] = $orderGoodsInfo['goodsName'];
        $data['orderNo'] = $order->orderNo;
        $data['desc'] = $desc;
        $data['orderId'] = $order->orderId;
        $data['goodsId'] = $orderGoodsInfo['goodsId'];
        $data['goodsImg'] = $orderGoodsInfo['goodsImg'];
        $data['createTime'] = '';
        $data['remark'] = $remark;
        $this->groupNewsData($data, $order->userId, $title, $noticType, $desc, $userInfo);
        return true;
    }

    /*
     * 订单待支付
     */
    public function orderWaitPay($order)
    {
        $noticType = 'orderWaitPay';
        $desc = '您有一笔订单未支付即将失效了哦，赶快支付吧~';
        $orderGoodsInfo = (new O())->getOrderGoodsByOrderId($order['orderId']);
        if (!$orderGoodsInfo) return true;
        $list = [$desc, $order['orderNo'], $order['realTotalMoney'].'元', $order['createTime']];
        $url = null;
        $wtn = new WTN();
        $u = new AMUser();
        $userInfo = $u->getFieldsById($order['userId'], 'userId, userPhone, nickname, userName, wxOpenId, isReceiveNews, receiveTime');
        if (!$userInfo) return true;
        if (!empty($userInfo->wxOpenId)){
            $path = config('weixin.template.wx.order_wait_pay.path') . $order['orderId'];
            $wtn->sendWxTemplate($list,'order_wait_pay',$userInfo->wxOpenId, '立即支付', $url, true, $path);
        }
        $title = '订单待支付提醒';
        $data['orderMoney'] = $order['realTotalMoney'].'元';
        $data['createTime'] = $order['createTime'];
        $data['desc'] = $desc;
        $data['orderId'] = $order['orderId'];
        $data['orderNo'] = $order['orderNo'];
        $data['goodsId'] = $orderGoodsInfo['goodsId'];
        $data['goodsImg'] = $orderGoodsInfo['goodsImg'];
        $data['goodsName'] = $orderGoodsInfo['goodsName'];
        $data['remark'] = '查看商品，再次下单';
        $this->groupNewsData($data, $order['userId'], $title, $noticType, $desc, $userInfo);
    }

    /*
     * 拼单成功
     */
    public function pintuanSuccess($orderId, $orderNo, $userId, $goods, $nicknameArr)
    {
        $wtn = new WTN();
        $u = new AMUser();
        $userInfo = $u->getFieldsById($userId, 'userId, userPhone, nickname, userName, wxOpenId, isReceiveNews, receiveTime');
        if (!$userInfo) return true;
        $url = null;
        $desc = '恭喜您'.$userInfo->nickname.'拼单成功，平台正在紧锣密鼓地准备发货~';
        $remark = '等待平台发货（平台承诺48小时内发货）';
        $list = [$desc, '每日优选拼团', $goods['goodsName'], $orderNo, '已支付', $goods['tuanNum']];
        if (!empty($userInfo->wxOpenId)){
            $path = config('weixin.template.wx.pintuan_order_success.path').$orderId;
            $wtn->sendWxTemplate($list,'pintuan_order_success',$userInfo->wxOpenId, $remark, $url, true, $path);
        }
        $noticType = 'pintuanSuccess';
        $title = '拼单成功通知';
        $msg['desc'] = $desc;
        $msg['orderId'] = $orderId;
        $msg['goodsImg'] = $goods['goodsImg'];
        $msg['goodsName'] = $goods['goodsName'];
        $msg['suiplusTime'] = '';
        $msg['suiplusNum'] = '';
        $msg['tuanUser'] = implode('、', $nicknameArr);
        $msg['orderStatus'] = '等待平台发货（平台承诺48小时内发货）';
        $msg['remark'] = '查看详情';
        $this->groupNewsData($msg, $userId, $title, $noticType, $desc, $userInfo);
    }

    /*
     * 拼单人数不足
     */
    public function pintuanPeopleLess($pintuanUser, $waitNotifyPintuan)
    {
        $wtn = new WTN();
        $u = new AMUser();
        $tuanInfo = (new Pintuans())->getPintuanByTuanId($pintuanUser['tuanId']);
        if (!$tuanInfo) return true;
        $pintuanTime = (int)floor((time() - $waitNotifyPintuan['createdAt'])/3600);
        $surplusTime = $waitNotifyPintuan['tuanTime'] - (time() - $waitNotifyPintuan['createdAt']);
        if ($surplusTime <= 0) return true;
        if (!in_array($pintuanTime, [1,4,8,23])) return true;
        $desc = '您的拼团订单已经进行了'.$pintuanTime.'小时，分享好友更容易拼单成功！';
        $userInfo = $u->getFieldsById($pintuanUser['userId'], 'userId, userPhone, nickname, userName, wxOpenId, isReceiveNews, receiveTime');
        $url = null;
        $remark = '点击查看拼单详情';
        $list = [$desc, $tuanInfo->goodsName, ceil($surplusTime/3600).'小时', $waitNotifyPintuan['needNum'].'人'];
        if (!$userInfo) return true;
        if (!empty($userInfo->wxOpenId)){
            $path = config('weixin.template.wx.pintuan_num_insufficient.path') . $pintuanUser['orderId'];
            $wtn->sendWxTemplate($list, 'pintuan_num_insufficient', $userInfo->wxOpenId, $remark, $url, true, $path);
        }
        $title = '拼单人数不足提醒';
        $noticType = 'pintuanPeopleLess';
        $userName = $userInfo->userName ?? $userInfo->nickname;
        $wtn->sendSms($userInfo->userPhone, 'pintuanNumInsufficient', json_encode(['userName'=>$userName]));
        $data['goodsName'] = $tuanInfo->goodsName;
        $data['suiplusTime'] = ceil($surplusTime/3600).'小时';
        $data['suiplusNum'] = $waitNotifyPintuan['needNum'].'人';
        $data['tuanUser'] = '';
        $data['orderStatus'] = '';
        $data['desc'] = $desc;
        $data['orderId'] = $pintuanUser['orderId'];
        $data['goodsImg'] = $tuanInfo->goodsImg;
        $data['remark'] = '立即分享';
        $this->groupNewsData($data, $pintuanUser['userId'], $title, $noticType, $desc, $userInfo);
    }

    /*
     * 商品发货
     */
    public function deliverGoods($orderId)
    {
        if(empty($orderId))return true;
        $o = new O();
        $wtn = new WTN();
        $u = new AMUser();
        $orderInfo = $o->getOrderById($orderId);
        if (!$orderInfo) return true;
        $orderGoodsInfo = $o->getOrderGoodsByOrderId($orderId);
        $expressName = isset($orderInfo->expressId) ? (new Express())->getExpressNameByExpressId($orderInfo->expressId) : '';
        $desc = '您购买的商品发货啦，点击查看物流信息';
        $remark = '查看物流';
        $userInfo = $u->getFieldsById($orderInfo->userId, 'userId, userPhone, nickname, userName, wxOpenId, isReceiveNews, receiveTime');
        if (!$userInfo) return true;
        if (!empty($userInfo->wxOpenId)){
            $url = null;
            $list = [$desc, $expressName, $orderInfo->expressNo, $orderGoodsInfo['goodsName'], $orderGoodsInfo['goodsNum']];
            $path = config('weixin.template.wx.deliver_goods.path') . $orderInfo->orderId;
            $wtn->sendWxTemplate($list, 'deliver_goods', $userInfo->wxOpenId, $remark, $url, true, $path);
        }
        $title = '商品发货通知';
        $noticType = 'deliverGoods';
        $data['expressName'] = $expressName;
        $data['expressNo'] = $orderInfo->expressNo;
        $data['goodsName'] = $orderGoodsInfo['goodsName'];
        $data['desc'] = $desc;
        $data['orderId'] = $orderInfo->orderId;
        $data['goodsImg'] = $orderGoodsInfo['goodsImg'];
        $data['remark'] = $remark;
        $this->groupNewsData($data, $orderInfo->userId, $title, $noticType, $desc, $userInfo);
    }

    /*
     * 红包领取通知
     */
    public function redPacketsReceive(\think\model $coupon, $userId)
    {
        if (!is_object($coupon) || empty($userId)) return true;
        $desc = '恭喜成功领取红包~';
        $title = '红包领取成功通知';
        $this->publicCouponData($coupon, $userId, $desc, $title,'redPacketsReceive');
    }

    /*
     * 红包过期提醒
     */
    public function redPacketsExpired($userId, $couponId)
    {
        if (empty($userId) || empty($couponId)) return true;
        $desc = '您的红包还有3天就过期了，赶快使用吧~';
        $title = '红包即将过期提醒';
        $coupon = (new Coupons())->getCouponsInfo(['couponId'=>$couponId]);
        $this->publicCouponData($coupon, $userId, $desc, $title, 'redPacketsExpired');
    }

    /*
     * 商品申请售后
     */
    public function refundApply($refundId)
    {
        if (empty($refundId)) return true;
        $wtn = new WTN();
        $u = new AMUser();
        $refundInfo = (new ORF())->getOrderRefunInfo(['id'=>$refundId]);
        if (!$refundInfo) return true;
        $orderGoodsInfo = (new O())->getOrderGoodsByOrderId($refundInfo->orderId);
        $desc = '您刚刚申请了商品售后~';
        $data['desc'] = $desc;
        $data['retundType'] = $refundInfo->type==1 ? '退货退款' : '仅退款';
        $data['goodsName'] = $orderGoodsInfo['goodsName'];
        $data['refundReson'] = (new ORF())->getRefundResaonName($refundInfo->refundReson)['dataName'];
        $data['remark'] = '查看详情';
        $userInfo = $u->getFieldsById($refundInfo->refundTo, 'userId, userPhone, nickname, userName, wxOpenId, isReceiveNews, receiveTime');
        if (!$userInfo) return true;
        $title = '商品申请售后提醒';
        $noticType = 'refundApply';
        $data['refundId'] = $refundId;
        $data['backMoney'] = '';
        $data['shopRejectReason'] = '';
        $data['goodsImg'] = $orderGoodsInfo['goodsImg'];
        $this->groupNewsData($data, $refundInfo->refundTo, $title, $noticType, $desc, $userInfo);
    }

    /*
     * 售后审核以及结果通知
     */
    public function refundAuditNotify(\think\model $order, \think\model $refund)
    {
        $wtn = new WTN();
        $u = new AMUser();
        $orderGoodsInfo = (new O())->getOrderGoodsByOrderId($order->orderId);
        if (!$orderGoodsInfo) return true;
        $userInfo = $u->getFieldsById($order->userId, 'userId, userPhone, nickname, userName, wxOpenId, isReceiveNews, receiveTime');
        if (!$userInfo) return true;
        $type = $refund->type==1 ? '退货退款' : '仅退款';
        $data['backMoney'] = '';
        $data['shopRejectReason'] = '';
        $userName = $userInfo->userName ?? $userInfo->nickname;
        if ($refund->refundStatus==1) {
            $desc = '您申请的商品退款，平台审核通过，退款成功~';
            $title = '商品申请售后成功';
            $noticType = 'refundSuccess';
            $data['backMoney'] = $refund->backMoney;
            $wtn->sendSms($refund->refundPhone, 'refundSuccess', json_encode(['userName'=>$userName,'goodsName'=>$this->filterGoodsName($orderGoodsInfo['goodsName'])]));
        } elseif ($refund->refundStatus==4) {
            $desc = '您申请的商品退款退货退款，平台审核通过，快去填写退货物流单号吧~';
            $title = '售后审核--填写物流单号提醒';
            $noticType = 'refundAudit';
            $list = ['first'=>$desc, 'HandleType'=>$type, 'Status'=>'退款中','RowCreateDate'=>$refund->createTime, 'LogType'=>'填写物流单号'];
            if (!empty($userInfo->wxOpenId)){
                $url = null;
                $path = config('weixin.template.wx.refund_schedule.path') . $refund->id;
                $wtn->sendWxTemplate($list, 'refund_schedule', $userInfo->wxOpenId, '查看详情', $url, true, $path);
            }
        }elseif ($refund->refundStatus==-1) {
            $desc = '您申请的商品退款/退货退款，平台审核未通过，退款失败！';
            $title = '商品申请售后失败';
            $noticType = 'refundFail';
            $data['shopRejectReason']= $refund->shopRejectReason;
            $wtn->sendSms($refund->refundPhone, 'refundFail', json_encode(['userName'=>$userName,'goodsName'=>$this->filterGoodsName($orderGoodsInfo['goodsName'])]));
        } else {
            return true;
        }
        $data['desc'] = $desc;
        $data['goodsName'] = $orderGoodsInfo['goodsName'];
        $data['retundType'] = $type;
        $data['refundReson'] = (new ORF())->getRefundResaonName($refund->refundReson)['dataName'];
        $data['remark'] = '查看详情';
        $data['refundId'] = $refund->id;
        $data['goodsImg'] = $orderGoodsInfo['goodsImg'];
        $this->groupNewsData($data, $order->userId, $title, $noticType, $desc, $userInfo);
    }

    /*
     * 每日签到提醒
     */
    public function signBoard($signUser)
    {
        $wtn = new WTN();
        $u = new AMUser();
        $userInfo = $u->getFieldsById($signUser['userId'], 'userId, userPhone, nickname, userName, wxOpenId, isReceiveNews, receiveTime');
        if (!$userInfo) return true;
        $title = '每日签到提醒';
        $signDay = fmod($signUser['continuousSignDays'],7);
        $day = $signDay==0 ? '今天' : (7-$signDay);
        $desc = $signDay==0 ? '您还没有签到，在坚持7天即可获得一次100%中奖机会~' : '您已经连续签到'.$signDay.'天，在坚持'.$day.'天即可获得一次100%中奖机会~';
        $data['desc'] = $desc;
        $data['userId'] = $signUser['userId'];
        $data['signDay'] = $signDay;
        $data['remark'] = '立即签到';
        $noticType = 'signBoard';
        $this->groupNewsData($data, $signUser['userId'], $title, $noticType, $desc, $userInfo);
    }

    /*
     * 金币奖励到账提醒
     */
    public function goldReward($sourceName, $gold, $userId)
    {
        $u = new AMUser();
        $wtn = new WTN();
        $desc = '任务完成'.$gold.'币奖励到账了，点击查看';
        $title = '金币奖励到账提醒';
        $noticType = 'goldReward';
        $userInfo = $u->getFieldsById($userId, 'userId, userPhone, nickname, userName, wxOpenId, isReceiveNews, receiveTime');
        if (!$userInfo) return true;
        $data['desc'] = $desc;
        $data['userId'] = $userId;
        $data['source'] = $sourceName;
        $data['remark'] = '查看详情';
        $this->groupNewsData($data, $userId, $title, $noticType, $desc, $userInfo);
    }

    public function getSystemNews($newsType, $offset, $pageSize)
    {
        $userId = ASUer::getUserByCache()['userId'];
        $userNews = new UserNews();
        $where['newsType'] = $newsType;
        $where['userId'] = $userId;
        $rs = $userNews->getUserNews($where,$offset, $pageSize);
        $rs['nowTime'] = time();
        $rs['domain'] = config('web.image_domain').'/';
        return $rs;
    }

    public function getAllNewsList()
    {
        $userId = ASUer::getUserByCache()['userId'];
        $userNews = new UserNews();
        $where['userId'] = $userId;
        $newsType = config('web.newsType');
        $data = [];
        foreach ($newsType as $k=>$v) {
            $where['newsType'] = $v;
            $data[$k] = $userNews->getNewsList($where, 'title, createTime');
            $data[$k]['newsType'] = $v;
            $data[$k]['list']['title'] = mb_substr($data[$k]['list']['title'], 0, 10);
        }
        $data['nowTime'] = time();
        return $data;
    }

    public function publicCouponData($coupon, $userId, $desc, $title, $noticType)
    {
        switch ($coupon->useCondition) {
            case 2:
                $couponValue = $coupon->couponValue.'折';
                $couponType = '折扣';
                break;
            case 1:
                $couponValue = $coupon->couponValue.'元';
                $couponType = '满减';
                break;
            default:
                $couponValue = $coupon->couponValue.'元';
                $couponType = '无门槛';
                break;
        }
        $surplusDay = $coupon->type==3 ? 30 : bcdiv(bcsub(strtotime($coupon->endDate), strtotime($coupon->startDate)), 86400);
        $data = [
            'desc'=>$desc,
            'couponValue'=> $couponValue,
            'couponType'=>$couponType,
            'surplusDay'=>$surplusDay.'天',
            'remark' => '立即使用'
        ];
        $u = new AMUser();
        $wtn = new WTN();
        $userInfo = $u->getFieldsById($userId, 'userId, userPhone, nickname, userName, wxOpenId, isReceiveNews, receiveTime');
        if ($noticType=='redPacketsExpired') {
            $userName = $userInfo->userName ?? $userInfo->nickname;
            $wtn->sendSms($userInfo->userPhone, 'couponExpire', json_encode(['userName'=>$userName, 'couponValue'=>$couponValue, 'expireDate'=>$coupon->endDate]));
        }
        $this->groupNewsData($data, $userId, $title, $noticType, $desc, $userInfo);
    }

    public function filterGoodsName($goodsName)
    {
        $name = mb_substr($goodsName, 0, 15).'..';
        return '['.$name.']';
    }

    public function groupNewsData($data, $userId, $title, $noticeType, $desc, $userInfo,$type=2, $newsId=0)
    {
        $msg = [
            'userId'=>$userId,
            'newsType'=>$type,
            'noticeType'=>$noticeType,
            'title'=>$title,
            'text'=>json_encode($data),
            'newsId'=>$newsId,
            'isRead'=>0,
            'createTime'=>time(),
        ];
        $wtn = new WTN();
        $wtn->sendXmPush($desc, $title, $userInfo, $msg);
        return (new UserNews())->insertUserNewsInfo($msg);
    }
}