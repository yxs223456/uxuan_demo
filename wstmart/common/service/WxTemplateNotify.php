<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/5
 * Time: 15:40
 */
namespace wstmart\common\service;

use wstmart\common\model\Users as AMUser;
use wstmart\common\model\OrderRefunds as ORF;
use wstmart\common\exception\AppException as AE;
use wstmart\common\model\Orders as O;
use wstmart\common\model\Coupons;
use wstmart\common\helper\WxTemplateNotify as WT;
use think\Db;
use wstmart\common\helper\XmPush;
use wstmart\common\helper\Sms;
use wstmart\app\model\AppSession;

class WxTemplateNotify
{
    /**
     * 商品售后审核提醒
     */
    public function afterSaleAudit(\think\model $order, \think\model $orderRefund, \think\model $weixinPayOrder)
    {
        $wxTemplateData = $this->getWxTemplateData($order->userId, 'after_sale_audit', $orderRefund->id);
        if (!empty($wxTemplateData)) {
            $templateConfig = config('weixin.template.mp.after_sale_audit');
            $templateInfo = [
                'userId' => $order->userId,
                'des' => $templateConfig['des'],
                'type' => 'afterSaleAudit',
                'targetId' => $orderRefund->id,
                'isSend' => 0,
                'wxClient' => 'miniPrograms',
                'appid' => $weixinPayOrder->appid,
            ];
            $userModel = (new AMUser())->getUserById($order->userId);
            $orderGoods = (new O())->getOrderGoodsByOrderId($order->orderId);;
            $uxuanData = DB::name('datas')->where('id', $orderRefund->refundReson)->find();
            $afterSaleStatus = $orderRefund->refundStatus == 1 ? '申请成功' : '申请失败';
            $goodsName = $orderGoods['goodsName'];
            $refundReason = $uxuanData['dataName'];
            $refundType = $orderRefund->type == 1 ? '退货退款' : '仅退款';
            $refundMoney = $orderRefund->backMoney;
            $keywords = [$afterSaleStatus, $goodsName, $refundReason, $refundType, $refundMoney];
            $wxTemplateData = json_decode($wxTemplateData['params'], true);
            $templateInfo['params'] = $this->groupMpTemplate($userModel->mpOpenId, $templateConfig['template_id'],
                $wxTemplateData['form_id'], $keywords, $wxTemplateData['page']);
            $this->addTemplateNotify($templateInfo);
        }
    }

    /**
     * 商品（退货退款）售后审核提醒
     */
    public function afterSaleGoodsAudit(\think\model $order, \think\model $orderRefund, \think\model $weixinPayOrder)
    {
        $wxTemplateData = $this->getWxTemplateData($order->userId, 'after_sale_goods_audit', $orderRefund->id);
        if (!empty($wxTemplateData)) {
            $templateConfig = config('weixin.template.mp.after_sale_goods_audit');
            $templateInfo = [
                'userId' => $order->userId,
                'des' => $templateConfig['des'],
                'type' => 'afterSaleGoodsAudit',
                'targetId' => $orderRefund->id,
                'isSend' => 0,
                'wxClient' => 'miniPrograms',
                'appid' => $weixinPayOrder->appid,
            ];
            $userModel = (new AMUser())->getUserById($order->userId);
            $orderGoods = (new O())->getOrderGoodsByOrderId($order->orderId);;
            $uxuanData = DB::name('datas')->where('id', $orderRefund->refundReson)->find();
            $afterSaleStatus = $orderRefund->refundStatus == 4 ? '待填写物流单号' : '申请失败';
            $goodsName = $orderGoods['goodsName'];
            $refundReason = $uxuanData['dataName'];
            $refundType = $orderRefund->type == 1 ? '退货退款' : '仅退款';
            $refundMoney = $orderRefund->backMoney;
            $keywords = [$afterSaleStatus, $goodsName, $refundReason, $refundType, $refundMoney];
            $wxTemplateData = json_decode($wxTemplateData['params'], true);
            $templateInfo['params'] = $this->groupMpTemplate($userModel->mpOpenId, $templateConfig['template_id'],
                $wxTemplateData['form_id'], $keywords, $wxTemplateData['page']);
            $this->addTemplateNotify($templateInfo);
        }
    }

    public function getWxTemplateData($userId, $type, $target)
    {
        $wxTemplateData = DB::name('mp_template_data')
            ->where('userId', $userId)
            ->where('type', $type)
            ->where('target', $target)
            ->find();
        return $wxTemplateData;
    }

    /*
     * 商品申请售后提醒
     */
    public function afterSaleApply($userId, $target, $page, $formId)
    {
        $templateConfig = config('weixin.template.mp.after_sale_apply');
        $userModel = (new AMUser())->getUserById($userId);
        $orf = new ORF();
        $orderFundInfo = $orf->getOrderRefundModelById($target);
        $orderReason = $orf->getRefundResaonName($orderFundInfo->refundReson)['dataName'];
        $orderGoodsInfo = (new O())->getOrderGoodsByOrderId($orderFundInfo->orderId);
        $type = $orderFundInfo->type==1 ? '退货退款' : '仅退款';
        $data = ['申请中', $orderGoodsInfo['goodsName'], $orderReason, $type, $orderFundInfo->backMoney];
        $templateInfo = [
            'userId' => $userId,
            'des' => $templateConfig['des'],
            'type' => 'afterSaleApply',
            'targetId' => $target,
            'isSend' => 0,
            'wxClient' => 'miniPrograms',
            'appid' => config('weixin.miniPrograms.appid'),
            'params' =>$this->groupMpTemplate($userModel->mpOpenId, $templateConfig['template_id'], $formId, $data, $page, null)
        ];
        $this->addTemplateNotify($templateInfo);
    }

    /*
     * 领取红包通知
     */
    public function couponReceive($userId, $target, $page, $formId)
    {
        $config = 'coupon_receive';
        $type = 'couponReceive';
        $this->couponPublicData($userId, $target, $page, $formId, $type, $config);
    }

    /*
     * 红包过期
     */
    public function couponTimeout($userId,$type='coupon_timeout',$couponId)
    {
        $wxTemplateData = $this->getWxTemplateData($userId, $type, $couponId);
        if (!empty($wxTemplateData)) {
            $wxTemplateDataArray = json_decode($wxTemplateData['params'], true);
            $config = 'couponTimeout';
            $this->couponPublicData($userId, $couponId, $wxTemplateDataArray['page'], $wxTemplateDataArray['form_id'], $config, $type);
        }
    }

    /*
     * 红包通用数据
     */
    public function couponPublicData($userId, $target, $page, $formId, $type, $config)
    {
        $templateConfig = config('weixin.template.mp.'.$config);
        $userModel = (new AMUser())->getUserById($userId);
        $couponInfo = (new Coupons())->getCouponInfoByCouponUserId($target);
        $useCondition = config('web.useCondition');
        $val = '';
        switch ($couponInfo->useCondition) {
            case 0:
                break;
            case 1:
                $val = '/满'.$couponInfo->useMoney;
                break;
            case 2:
                $val = $couponInfo->useMoney.'折';
                break;
        }
        $time = bcsub(strtotime($couponInfo->endDate . ' 23:59:59'), time());
        $expiryTime = ceil($time/86400);
        $couponValue = $couponInfo->couponValue.$val;
        $couponType = $useCondition[$couponInfo->useCondition];
        $data = [$couponValue, $couponType, $expiryTime.'天'];
        $templateInfo = [
            'userId' => $userId,
            'des' => $templateConfig['des'],
            'type' => $type,
            'targetId' => $target,
            'isSend' => 0,
            'wxClient' => 'miniPrograms',
            'appid' => config('weixin.miniPrograms.appid'),
            'params' =>$this->groupMpTemplate($userModel->mpOpenId, $templateConfig['template_id'], $formId, $data, $page, null)
        ];
        $this->addTemplateNotify($templateInfo);
    }

    /*
     * 订单取消通知(用户主动取消)
     */
    public function cancelOrderUser($userId, $orderId, $page, $formId)
    {
        $templateConfig = config('weixin.template.mp.cancel_order_user');
        $userModel = (new AMUser())->getUserById($userId);
        $orderModel = new O();
        $order = $orderModel->getOrderById($orderId);
        $orderGoods = $orderModel->getOrderGoodsByOrderId($orderId);
        $keywords = ['主动取消', $order['realTotalMoney'] . '元', $order['orderNo'], $orderGoods['goodsName']];
        $templateInfo = [
            'userId' => $userId,
            'des' => $templateConfig['des'],
            'type' => 'cancelOrder',
            'targetId' => $orderId,
            'isSend' => 0,
            'wxClient' => 'miniPrograms',
            'appid' => config('weixin.miniPrograms.appid'),
            'params' =>$this->groupMpTemplate($userModel->mpOpenId, $templateConfig['template_id'], $formId, $keywords, $page)
        ];
        $this->addTemplateNotify($templateInfo);
    }

    /*
     * 订单取消通知(超时取消)
     */
    public function cancelOrderTimeout(\think\model $order, $orderGoods)
    {
        $wxTemplateData = $this->getWxTemplateData($order->userId, 'cancel_order_timeout', $order->orderId);
        if (!empty($wxTemplateData)) {
            $templateConfig = config('weixin.template.mp.cancel_order_timeout');
            $userModel = (new AMUser())->getUserById($order->userId);
            $map = [
                'userId' => $userModel->userId,
                'type' => 'orderTimeout',
                'targetId' => $order->orderId,
            ];
            $notifyMes = $this->getTemplateNotify($map);
            if ($notifyMes) {
                return;
            }
            $keywords = ['您的订单因半小时未支付，已自动取消！', $order['realTotalMoney'] . '元', $order['orderNo'], $orderGoods['goodsName']];
            $wxTemplateData = json_decode($wxTemplateData['params'], true);
            $templateInfo = [
                'userId' => $userModel->userId,
                'des' => $templateConfig['des'],
                'type' => 'orderTimeout',
                'targetId' => $order->orderId,
                'isSend' => 0,
                'wxClient' => 'miniPrograms',
                'appid' => config('weixin.miniPrograms.appid'),
                'params' =>$this->groupMpTemplate($userModel->mpOpenId, $templateConfig['template_id'],
                    $wxTemplateData['form_id'], $keywords, $wxTemplateData['page'])
            ];
            $this->addTemplateNotify($templateInfo);
        }
    }

    public function orderWaitPay($order)
    {
        $wxTemplateData = $this->getWxTemplateData($order['userId'], 'order_wait_pay', $order['orderId']);
        if (!empty($wxTemplateData)) {
            $templateConfig = config('weixin.template.mp.order_wait_pay');
            $userModel = (new AMUser())->getUserById($order['userId']);
            $orderModel = new O();
            $orderGoods = $orderModel->getOrderGoodsByOrderId($order['orderId']);
            $keywords = [$orderGoods['goodsName'], $order['orderNo'], $order['realTotalMoney'] . '元'];
            $wxTemplateData = json_decode($wxTemplateData['params'], true);
            $templateInfo = [
                'userId' => $userModel->userId,
                'des' => $templateConfig['des'],
                'type' => 'orderWaitPay',
                'targetId' => $order['orderId'],
                'isSend' => 0,
                'wxClient' => 'miniPrograms',
                'appid' => config('weixin.miniPrograms.appid'),
                'params' =>$this->groupMpTemplate($userModel->mpOpenId, $templateConfig['template_id'],
                    $wxTemplateData['form_id'], $keywords, $wxTemplateData['page'])
            ];
            $this->addTemplateNotify($templateInfo);
        }
    }

    public function pintuanShortNotify($pintuanUser, $waitNotifyPintuan, $weixinPayOrder)
    {
        $templateConfig = config('weixin.template.mp.pintuan_user_short');
        $surplusTime = $waitNotifyPintuan['tuanTime'] - (time() - $waitNotifyPintuan['createdAt']);
        if ($surplusTime <= 0) return;
        $surplusTime = '还剩' . ceil($surplusTime/3600) . '小时';
        $pintuanTime = floor((time() - $waitNotifyPintuan['createdAt'])/3600);
        if ($pintuanTime == 1) {
            $type = 'pintuanUserShort1Hour';
            $wxTemplateData = $this->getWxTemplateData($pintuanUser['userId'], 'pintuan_short_1_hour', $pintuanUser['orderId']);
        } elseif ($pintuanTime == 4) {
            $type = 'pintuanUserShort4Hour';
            $wxTemplateData = $this->getWxTemplateData($pintuanUser['userId'], 'pintuan_short_2_hour', $pintuanUser['orderId']);
        } elseif ($pintuanTime == 8) {
            $type = 'pintuanUserShort8Hour';
            $wxTemplateData = $this->getWxTemplateData($pintuanUser['userId'], 'pintuan_short_3_hour', $pintuanUser['orderId']);
        } elseif ($pintuanTime == 23) {
            $type = 'pintuanUserShort23Hour';
            $wxTemplateData = $this->getWxTemplateData($pintuanUser['userId'], 'pintuan_short_4_hour', $pintuanUser['orderId']);
        } else {
            return;
        }
        if (empty($wxTemplateData)) return;
        $wxTemplateData = json_decode($wxTemplateData['params'], true);
        $userModel = (new AMUser())->getUserById($pintuanUser['userId']);
        $map = [
            'userId' => $userModel->userId,
            'type' => $type,
            'targetId' => $waitNotifyPintuan['id'],
        ];
        $notifyMes = $this->getTemplateNotify($map);
        if ($notifyMes) {
            return;
        }
        $orderGoods = (new O())->getOrderGoodsByOrderId($pintuanUser['orderId']);
        $keywords = ["您的拼团订单已经进行了{$pintuanTime}小时，分享好友更容易拼单成功！", $surplusTime, $orderGoods['goodsName'], $waitNotifyPintuan['needNum']];
        $templateInfo = [
            'userId' => $userModel->userId,
            'des' => $templateConfig['des'],
            'type' => $type,
            'targetId' => $waitNotifyPintuan['id'],
            'isSend' => 0,
            'wxClient' => 'miniPrograms',
            'appid' => $weixinPayOrder['appid'],
            'params' =>$this->groupMpTemplate($userModel->mpOpenId, $templateConfig['template_id'],
                $wxTemplateData['form_id'], $keywords, $wxTemplateData['page'])
        ];
        $this->addTemplateNotify($templateInfo);
    }

    public function pintuanSuccessNotify($pintuanUser, $goodsName, $usersNickname, $weixinPayOrder, $tuanOrderId, $mpOpenId)
    {
        $templateConfig = config('weixin.template.mp.pintuan_success');
        $map = [
            'userId' => $pintuanUser['userId'],
            'type' => 'pintuanSuccess',
            'targetId' => $tuanOrderId,
        ];
        $notifyMes = $this->getTemplateNotify($map);
        if ($notifyMes) {
            return;
        }
        $keywords = ['恭喜您拼单成功，平台正在紧锣密鼓地准备发货~', $goodsName, implode('、', $usersNickname), '等待平台发货（平台承诺48小时内发货）'];
        $templateInfo = [
            'userId' => $pintuanUser['userId'],
            'des' => $templateConfig['des'],
            'type' => 'pintuanSuccess',
            'targetId' => $tuanOrderId,
            'isSend' => 0,
            'wxClient' => 'miniPrograms',
            'appid' => $weixinPayOrder['appid'],
            'params' =>$this->groupMpTemplate($mpOpenId, $templateConfig['template_id'],
                $weixinPayOrder['prepay_id'], $keywords, 'pages/orderDetails/main?orderId=' . $pintuanUser['orderId'])
        ];
        $this->addTemplateNotify($templateInfo);
    }

    public function deliverNotify($order, $weixinPayOrder)
    {
        $templateConfig = config('weixin.template.mp.order_deliver');
        $map = [
            'userId' => $order['userId'],
            'type' => 'orderDeliver',
            'targetId' => $order['orderId'],
        ];
        $notifyMes = $this->getTemplateNotify($map);
        if ($notifyMes) {
            return;
        }
        $userModel = (new AMUser())->getUserById($order['userId']);
        $orderGoods = (new O())->getOrderGoodsByOrderId($order['orderId']);
        $express = model('express')->get($order['expressId']);
        $keywords = ['您购买的商品发货啦，点击查看物流信息', $orderGoods['goodsName'], $order['expressNo'], $express->expressName];
        $templateInfo = [
            'userId' => $order['userId'],
            'des' => $templateConfig['des'],
            'type' => 'orderDeliver',
            'targetId' => $order['orderId'],
            'isSend' => 0,
            'wxClient' => 'miniPrograms',
            'appid' => $weixinPayOrder['appid'],
            'params' =>$this->groupMpTemplate($userModel->mpOpenId, $templateConfig['template_id'],
                $weixinPayOrder['prepay_id'], $keywords, 'pages/logisticsInfo/main?orderId=' . $order['orderId'])
        ];
        $this->addTemplateNotify($templateInfo);
    }

    public function groupMpTemplate($mpOpenId, $templateId, $formId, array $data, $page=null, $emphasisKeyword=null)
    {
        if (empty($data)) throw AE::factory(AE::WX_TEMPLATE_EMPTY);
        foreach ($data as $k=>$v) {
            $content['keyword'.($k+1)] = ['value'=>$v];
        }
        $msg = [
            'touser' => $mpOpenId,
            'template_id' => $templateId,
            'page' => $page,
            'form_id' => $formId,
            'emphasis_keyword' => $emphasisKeyword,
            'data'=>$content
        ];
        return json_encode($msg);
    }

    public function addTemplateNotify($data)
    {
        DB::name('wx_template_notify')->insert($data);
    }

    public function getTemplateNotify($map)
    {
        $notify = DB::name('wx_template_notify')
            ->where($map)
            ->find();
        return $notify;
    }

    /*
     * 发送微信服务号消息
     */
    public function sendWxTemplate($data, $type, $openId, $remark='', $url=null, $isMiniprograms=false, $pagepath=null)
    {
        if (empty($data) || empty($type) || empty($openId)) {
            return true;
        }
        $wt = new WT();
        $miniprogramAppid = $isMiniprograms ? config('weixin.miniPrograms.appid') : null;
        $templateId = config('weixin.template.wx.'.$type.'.template_id');
        $msg = $wt->filterWeixinTemplate($data, $remark, $openId, $url, $templateId, $miniprogramAppid, $pagepath);
        $appid = config('weixin.publicNumber.appid');
        $wt->sendWeixinTemplate($msg,$appid);
        return true;
    }

    /*
     * 发送小米推送
     * 根据regid
     */
    public function sendXmPush($payload, $title, $userInfo, $extra=[], $desc='')
    {
        if (empty($userInfo) || empty($title) || empty($payload)) {
            return true;
        }
        if ($userInfo->isReceiveNews==1) {
            if (!empty($userInfo->receiveTime)) {
                list($start, $end) = explode('_', $userInfo->receiveTime);
                if ($start > $end) {
                    if (date('H:i') > $start || date('H:i') < $end) return true;
                } else {
                    if (date('H:i') > $start && date('H:i') < $end) return true;
                }
            }else {
                return true;
            }
            $xmPush = new XmPush();
            $appSession = new AppSession();
            $info = $appSession->getTokenInfo(['userId'=>$userInfo->userId, 'type'=>'app'], 'xmRegid,system');
            //$regid = '9cb6BZa2xmaQ3iAbLohlr8OloLayMBEU4Ip+wU/hRki9PhSLy+F6ZMzQkdwsBWGT';
            //$regid = 'IK2zNHv0Ut5sIX97xI4V02hn7qtcpUDDVZgQ6PKE5MECkRIwDL8pplLS24nf6hs3';
            //$xmPush->pushRegidToIos($title, $payload, $extra, $desc, $regid);
            //$xmPush->pushRegidToAndroid($title, $payload, $extra, $regid);
            if (empty($info->xmRegid)) return true;
            if (strpos(strtolower($info->system),'android')!==false) {
                $xmPush->pushRegidToAndroid(1, '', $payload, $extra, $info->xmRegid);
            } elseif (strpos(strtolower($info->system),'ios')!==false) {
                $xmPush->pushRegidToIos($title, $payload, $extra, $desc, $info->xmRegid);
            } else {
                return true;
            }
        }
        return true;
    }

    public function sendXmPushToAll($payload, $title, $extra=[], $desc='')
    {
        if (empty($title) || empty($payload)) {
            return true;
        }
        $xmPush = new XmPush();
        $xmPush->pushRegidToAndroid($title, $payload, $extra);
        $xmPush->pushRegidToIos($title, $payload, $extra, $desc);
        return true;
    }

    /*
     * 发送短信消息
     */
    public function sendSms($phone,$type,$param)
    {
        if (empty($phone) || empty($type) || empty($param)) {
            return true;
        }
        $timplateId = config('sms.user_news_template.'.$type);
        $sign = config('sms.sms_sign');
        $sms = new Sms();
        $sms->sendSms($phone,$timplateId, $param, $sign);
        return true;
    }
}