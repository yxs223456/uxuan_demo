<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/6/22
 * Time: 11:25
 */

namespace wstmart\common\service;

use function Qiniu\base64_urlSafeDecode;
use wstmart\common\service\Orders as CSOrders;
use wstmart\common\exception\AppException as AE;
use think\facade\Log;
use wstmart\common\model\Orders;
use wstmart\app\model\Orders as O;
use Config;
use wstmart\common\model\Payments as P;
use wstmart\common\service\Pintuan;
use think\Db;
//use wstmart\app\model\Users;
use wstmart\common\model\Users as U;
use wstmart\common\helper\WeixinPay;
use wstmart\common\service\Users as SU;
use wstmart\app\service\Users as ASUser;
use wstmart\common\model\OrderRefunds as ORF;
use wstmart\common\helper\Dingding as DD;

class Weixin
{
    /*
     * 统一下单
     * @$orderNo 订单号
     * @$type 微信支付类型
     * @$tradeStatus 密钥支付类型 1-微信, 2-支付宝
     */
    public function weiXinUnifiedOrder($orderNo, $type='app', $tradeStatus=1)
    {
        if ($tradeStatus==1) {
            $order = new Orders();
            $where['orderNo'] = $orderNo;
            $orderInfo = $order->getOrderByOrderNo($where);
            if (empty($orderInfo)) {
                throw AE::factory(AE::ORDER_NOT_EXISTS);
            }
            if ($orderInfo['orderStatus']!=-2) {
                throw AE::factory(AE::ORDER_STATUS_NOT_EXISTS);
            }
            if ($orderInfo['isPay']==1) {
                throw AE::factory(AE::ORDER_PAY_ALREADY);
            }
            $time = strtotime($orderInfo['createTime'])+config('web.order_timeout');
            if (time() > $time) {
                throw AE::factory(AE::ORDER_TIMEOUT_ALREADY);
            }
            return $this->payTypeDel($orderInfo, $type);
        } elseif($tradeStatus==2) {
            return true;
        }
    }

    //各种微信端的支付处理
    public function payTypeDel($orderInfo, $type)
    {
        $conf = $this->basicConfig($type);
        $body = $conf['appName'].'-'.$orderInfo['goodsName'];
        $body = strlen($body)<=128 ? $body : mb_substr($body, 0, 41).'...';
        $orderInfo['body'] = $body;
        $orderInfo['trade_type'] = $conf['trade_type'];
        $orderInfo['appid'] = $conf['appid'];
        $orderInfo['type'] = 'orderPay';
        $orderInfo['targetId'] = $orderInfo['orderId'];
        unset($orderInfo['orderId']);
        $msg = $this->weChatApp($orderInfo, $conf, $type);
        return $msg;
    }

    /*
     * 微信预支付统一下单
     */
    public function weChatApp($arr, $conf, $type)
    {
        if (empty($arr)) {
            throw AE::factory(AE::COM_PARAMS_EMPTY);
        }
        if ($conf['trade_type']=='JSAPI') {
            $userId = ASUser::getUserByCache()['userId'];
            $userInfo = model('common/Users')->getUserById($userId);
            if ($type=='miniPrograms') {
                if (empty($userInfo->mpOpenId)) throw AE::factory(AE::WECHAT_BIND_MINIPROGRAMS);
                $arr['openid'] = $userInfo->mpOpenId;
            } else {
                if (empty($userInfo->wxOpenId)) throw AE::factory(AE::WECHAT_BIND_PUBLIC);
                $arr['openid'] = $userInfo->wxOpenId;
            }
        }
        $arr['out_trade_no'] = time().getRand(8);
        $arr['spbill_create_ip'] = getClientIp();
        $arr['realTotalMoney'] = bcmul($arr['realTotalMoney'],100);
        $arr['time_start'] = date('YmdHis', time());
        $arr['notify_url'] = config('web.domain').config('web.wx_app_notify_url');
        $arr['time_expire'] = date('YmdHis', strtotime($arr['createTime'])+config('web.order_timeout')-5);
        $wp = new WeixinPay();
        $res = $wp->wechatUnifiedOrder($arr);
        if (!$res) {
            throw AE::factory(AE::COM_PARAMS_EMPTY);
        }
        if ($res['return_code']=='SUCCESS' && $res['result_code']=='SUCCESS') {
            $returnParams = $this->filteWxOrderData($res, $conf, $type);
            $payData['type'] = $conf['mchid'];
            $arr['mch_id'] = $res['mch_id'];
            $arr['prepay_id'] = $res['prepay_id'];
            $this->insertWxOrderData($arr, $type);
            return $returnParams;
        } else {
            $dd = new DD();
            $dd->senMessage(json_encode(['msgtype'=>'text','text'=>['content'=>'WeixinPay||订单id'.$arr['targetId'].'||'.date('Y-m-d H:i:s').'||'.json_encode($res, JSON_UNESCAPED_UNICODE)]]), ['陈小军']);
            throw AE::factory(AE::WECHAT_UNIFIEDORDER_ERROR);
        }
    }

    /*
     * 处理微信退款
     * @type 1-自动退款，2-平台退款
     */
    public function wechatRefundFlow($orderId, $type=1)
    {
        $map['orderId'] = $orderId;
        $wechatPayInfo = model('common/Orders')->getWechatPayOrderInfo($map);
        $refundsInfo = model('common/OrderRefunds')->getRefundData($orderId);
        if ($refundsInfo['refundStatus']!=1) throw AE::factory(AE::REFUND_INSERT_FAIL);
        $expirTime = time()-strtotime($wechatPayInfo['time_end']);
        if ($expirTime > 365*86400) throw AE::factory(AE::WECHAT_REFUND_TIME_EXPIR);
        Db::startTrans();
        try{
            $wechatPayInfo['refund_desc'] = $refundsInfo['name'];
            $wechatPayInfo['refund_fee'] = $refundsInfo['backMoney']*100;
            $this->insertWxRefundData($wechatPayInfo);
            $o = new O();
            $updateStatus = $o->updateOrderData(['orderId'=>$wechatPayInfo['orderId']], ['isRefund'=>1, 'afterSaleStatus'=>2]);
            if(!$updateStatus) throw AE::factory(AE::DATA_UPDATE_FAIL);
            if ($wechatPayInfo['type']=='orderRefund') {
                $p = new Pintuan();
                if ($wechatPayInfo['isPintuan']==1) {
                    $p->afterAfterSaleRefund($wechatPayInfo['targetId']);
                }
            }
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            $dd = new DD();
            Log::write($e->getMessage(), 'error');
            $dd->senMessage(json_encode(['msgtype'=>'text','text'=>['content'=>'WeixinRefund----'.$e->getMessage()]]), ['陈小军']);
            Db::rollback();
            throw $e;
        }
    }

    /*
     * 处理微信申请退款返回的数据
     */
    public function insertWxRefundData($res)
    {

        $payData['type'] = 'orderRefund';
        $payData['targetId'] = $res['orderId'];
        $payData['appid'] = $res['appid'];
        $payData['mch_id'] = $res['mch_id'];
        $payData['out_refund_no'] = getRand(16).time();
        $payData['out_trade_no'] = $res['out_trade_no'];
        $payData['transaction_id'] = $res['transaction_id'];
        $payData['refund_desc'] = $res['refund_desc'];
        $payData['refund_fee']= $res['refund_fee'];
        $payData['total_fee'] = $res['total_fee'];
        $payData['isRefund'] = 0;
        $p = new P();
        $result = $p->insertWechatRefundData($payData);
        if (!$result) {
            throw AE::factory(AE::WECHAT_UNIFIEDORDER_FAIL);
        }
        return $result;
    }

    /*
     * 查询退款状态的后续处理
     * 订单表和支付标的状态，以及退款表的数据修改
     */
    public function delrefundQueryStatus($arr, $orderId, $type)
    {
        $data = [];
        $p = new P();
        $map['transaction_id']= $arr['transaction_id'];
        if ($arr['refund_status_0']=='SUCCESS') {
            $o = new O();
            $data['isRefund']= 1;
            $p->updateWechatOrder($map, $data);
            $o->updateOrderData(['orderId'=>$orderId], $data);
            $orderRefundData = ['refundTradeNo'=>$arr['out_refund_no_0'], 'refundTime'=>$arr['refund_success_time_0'],'refundStatus'=>$type];
            model('common/OrderRefunds')->updateRefundData(['id'=>$arr['refundId']], $data);
            $data['refund_success_time_0']= $arr['refund_success_time_0'];
        } elseif ($arr['refund_status_0']=='PROCESSING') {
            $data['isRefund']= 0;
        } elseif ($arr['refund_status_0']=='REFUNDCLOSE') {
            $data['isRefund']= 0;
        } else {
            throw AE::factory(AE::REFUND_WECHAT_ERROR);
        }
        $data['result_code']= $data['refund_status_0'] =$arr['refund_status_0'];
        $data['refund_recv_accout_0']= $arr['refund_recv_accout_0'];
        $p->updateWechatRefundOrder($map,$data);
        return true;
    }

    /*
     * 处理统一下单返回的数据
     */
    public function filteWxOrderData($res, $conf, $type)
    {
        if ($type=='app') {
            return $this->appReturnData($res, $conf);
        } elseif($type=='miniPrograms') {
            return $this->miniProgramsReturnData($res, $conf);
        } elseif($type=='publicNumber') {
            return $this->publicReturnData($res, $conf);
        } elseif($type=='h5') {
            return $this->h5ReturnData( $res, $conf);
        } else {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
    }

    //app统一下单返回参数
    public function appReturnData($res, $conf)
    {
        $data = [
            'appid'=>$res['appid'],
            'prepayid'=>$res['prepay_id'],
            'package'=>'Sign=WXPay',
            'timestamp'=>time(),
            'noncestr'=>getRand(),
        ];
        $data['partnerid'] = $conf['mchid'];
        $data['sign'] = $this->createSign($data, $conf['key']);
        unset($data['package']);
        $data['package_val'] = 'Sign=WXPay';
        return $data;
    }

    //小程序统一下单返回参数
    public function miniProgramsReturnData($res, $conf)
    {
        $data = [
            'appId'=>$res['appid'],
            'timeStamp'=>time(),
            'nonceStr'=>getRand(),
            'package'=>'prepay_id='.$res['prepay_id'],
            'signType'=>'MD5',
        ];
        $data['paySign'] = $this->createSign($data, $conf['key']);
        unset($data['appId']);
        return $data;
    }

    //公众号支付统一下单返回参数
    public function publicReturnData($res, $conf)
    {
        $data = [
            'appId'=>$res['appid'],
            'timeStamp'=>time(),
            'nonceStr'=>getRand(),
            'package'=>'prepay_id='.$res['prepay_id'],
            'signType'=>'MD5',
        ];
        $data['paySign'] = $this->createSign($data, $conf['key']);
        return $data;
    }

    //h5支付统一下单返回参数
    public function h5ReturnData($res, $conf)
    {
        $data = [
            'appId'=>$res['appid'],
            'timeStamp'=>time(),
            'nonceStr'=>getRand(),
            'package'=>'prepay_id='.$res['prepay_id'],
            'signType'=>'MD5',
        ];
        $data['paySign'] = $this->createSign($data, $conf['key']);
        return $data;
    }

    //添加微信订单数据
    public function insertWxOrderData($data, $type)
    {
        $payData['type'] = $data['type'];
        $payData['wechatType'] = $type;
        $payData['targetId'] = $data['targetId'];
        $payData['appid'] = $data['appid'];
        $payData['body'] = $data['body'];
        $payData['prepay_id'] = $data['prepay_id'];
        $payData['orderNo'] = $data['orderNo'];
        $payData['mch_id'] = $data['mch_id'];
        $payData['out_trade_no'] = $data['out_trade_no'];
        $payData['spbill_create_ip'] = $data['spbill_create_ip'];
        $payData['total_fee']= $data['realTotalMoney'];
        $payData['time_expire'] = $data['time_expire'];
        $payData['time_start'] = $data['time_start'];
        $payData['limit_pay'] = 'no_credit';
        $payData['trade_state'] = 'InPayment';
        $payData['isPay'] = 0;
        $p = new P();
        $result = $p->insertWechatOrder($payData);
        if (!$result) {
            throw AE::factory(AE::WECHAT_UNIFIEDORDER_FAIL);
        }
        return $result;
    }

    public function updateOrderData($arr)
    {
        $o = new O();
        $ma['orderNo'] = $arr['orderNo'];
        $result = $o->updateOrderData($ma, ['payType'=>1]);
        return $result;
    }

    /*
     * 处理微信支付结果
     */
    public function delWeixinNotify($xml)
    {
        require ROOT_PATH . 'extend/weixin/WxPay.Data.php';
        $wxPayResult = new \WxPayResults();
        //如果返回成功则验证签名
        Db::startTrans();
        try {
            $result = $wxPayResult::Init($xml);
            if ($result['result_code']!='SUCCESS') {
                $err['return_code'] = 'FAIL';
                $err['return_msg'] = $result['err_code'];
                return $this->arrayToXml($err);
            }
            $success['return_code'] = 'SUCCESS';
            $p = new P();
            $where['out_trade_no'] = $result['out_trade_no'];
            //订单是否已处理
            //处理是否加锁
            $re = $p->getWechatOrder($where);
            if ($re['isPay']==1 && $re['trade_state'] =='SUCCESS') {
                return $this->arrayToXml($success);
            }
            if ($re['total_fee']!=$result['total_fee']) {
                return $this->arrayToXml($success);
            }
            $arr = $this->weChatDelData($result);
            $payOrder = $p->getWxPayOrderByOutTradeNo($result['out_trade_no']);
            $updateWechatOrder = $p->updateWechatOrder($where, $arr);
            if (!$updateWechatOrder) {
                $error['return_code'] = 'FAIL';
                $error['return_msg'] = '处理失败';
                return $this->arrayToXml($error);
            }
            if ($payOrder['type'] == 'orderPay') {
                $this->afterPayOrder($payOrder['targetId'], $result);
            }
            Db::commit();
            return $this->arrayToXml($success);
        } catch (\Throwable $e){
            Db::rollback();
            $dd = new DD();
            $message = json_encode([
                'exceptionClass' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            Log::write($message, 'error');
            $dd->senMessage(json_encode(['msgtype'=>'text','text'=>['content'=>'WeixinNotify----'.$message]]), ['陈小军']);
            throw $e;
        }
    }

    protected function afterPayOrder($orderId, $result)
    {
        log::write('afterPayOrder_' . $orderId, 'info');
        $order = (new Orders())->getOrderById($orderId);
        $orderStatus = 0;
        if ($order['isPintuan']==1) {
            $orderStatus = -4;
        }
        $data['orderStatus'] = $orderStatus;
        $data['payType'] = 1;
        $data['payFrom'] = 'weixinpays';
        $data['isPay'] = 1;
        $data['isClosed'] = 0;
        $data['tradeNo'] = $result['transaction_id'];
        $data['payTime'] = date('Y-m-d H:i:s', strtotime($result['time_end']));
        $data['totalPayFee'] = $result['total_fee'];
        $order->save($data, ['orderId', $orderId]);
        if ($order['isPintuan']==1) {
            $pin = new Pintuan();
            $pin->afterPay($order['orderId']);
        } elseif (preg_match('/^2_/', $order['pid'])) {
            (new CSOrders())->fanliCallback($order['orderId']);
        }
    }

    /*
     * 数据处理
     */
    public function weChatDelData($result)
    {
        if (!is_array($result) && empty($result)) {
            throw AE::factory(AE::COM_PARAMS_EMPTY);
        }

        $data['trade_state'] = 'SUCCESS';
        $data['openid'] = $result['openid'];
        $data['trade_type'] = $result['trade_type'];
        $data['bank_type'] = $result['bank_type'];
        $data['total_fee'] = $result['total_fee'];
        $data['transaction_id'] = $result['transaction_id'];
        $data['time_end'] = $result['time_end'];
        $data['isPay'] = 1;
        return $data;
    }

    public function arrayToXml($arr)
    {
        if(!is_array($arr) || count($arr) <= 0)
        {
            return '数组异常';
        }
        $xml = "<xml>";
        foreach ($arr as $key=>$val)
        {
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }

    /*
     * app基础配置
     */
    public function basicConfig($type)
    {
        $conf = [];
        $config = config('weixin.'.$type);
        $conf['appid'] = $config['appid'];
        $conf['appsecret'] = $config['appsecret'];
        $conf['key'] =  $config['mchkey'];
        $conf['trade_type'] =  $config['trade_type'];
        $conf['notify_url'] = config('web.wx_app_notify_url');
        $conf['appName'] = config('web.wx_pay_tag');
        $conf['mchid'] = $config['mch_id'];
        $conf['sslcert_path'] = $config['sslcert_path'];
        $conf['sslkey_path'] = $config['sslkey_path'];
        return $conf;
    }

    /**
     * 生成签名
     * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
     */
    public function createSign($arr, $mchkey)
    {

        //签名步骤一：按字典序排序参数
        ksort($arr);
        $string = $this->ToUrlParams($arr);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=".$mchkey;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;

    }

    /**
     * 格式化参数格式化成url参数
     */
    public function ToUrlParams($arr)
    {
        $buff = "";
        foreach ($arr as $k => $v)
        {
            if($k != "sign" && $v != "" && !is_array($v)){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     * 过滤微信参数信息
     * */
    public function filterWechatInfo($params, $userId)
    {
        $cont = [];
        if (!is_array($params)) {
            return false;
        }
        $cont['userId'] = $userId;
        $cont['userName'] = $params['nickname'];
        $cont['userSex'] = $params['sex'];
        $cont['province'] = $params['province'];
        $cont['city'] = $params['city'];
        $cont['country'] = $params['country'];
        $cont['userPhoto'] = $params['headimgurl'];
        $cont['privilege'] = $params['privilege'];
        return $cont;
    }

}