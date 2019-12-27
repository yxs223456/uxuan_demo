<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/21
 * Time: 10:35
 */
namespace wstmart\common\helper;

use wstmart\common\exception\AppException as AE;
use think\facade\Log;

include_once \Env::get('root_path') . 'extend/weixin/WxPay.Data.php';
include_once \Env::get('root_path') . 'extend/weixin/WxPay.Api.php';
class WeixinPay
{
    public function getWxMchConfigByAppid($appid)
    {
        $config = config('weixin.mch.' . $appid);
        if (empty($config)) {
            throw AE::factory(AE::WECHAT_MCH_NOT_EXISTS);
        }
        return $config;
    }

    /**
     * 向微信发送退款请求
     */
    public function wxRefund(array $wxRefundParams)
    {
        $wxRefund = new \WxPayRefund();
        $wxRefund->SetTransaction_id($wxRefundParams['transaction_id']);
        $wxRefund->SetOut_refund_no($wxRefundParams['out_refund_no']);
        $wxRefund->SetTotal_fee($wxRefundParams['total_fee']);
        $wxRefund->SetRefund_fee($wxRefundParams['refund_fee']);
        $wxRefund->SetRefund_desc($wxRefundParams['refund_desc']);
        $wxRefund->SetRefund_account('REFUND_SOURCE_RECHARGE_FUNDS');

        $mchConfig = $this->getWxMchConfigByAppid($wxRefundParams['appid']);
        new \WxPayConfig($mchConfig);
        $result = \WxPayApi::refund($wxRefund);
        return $result;
    }

    /**
     * 向微信查询退款进度
     */
    public function wxRefundQuery(array $wxRefundQueryParams)
    {
        $wxRefundQuery = new \WxPayRefundQuery();
        $wxRefundQuery->SetOut_refund_no($wxRefundQueryParams['out_refund_no']);

        $mchConfig = $this->getWxMchConfigByAppid($wxRefundQueryParams['appid']);
        new \WxPayConfig($mchConfig);
        $result = \WxPayApi::refundQuery($wxRefundQuery);
        return $result;
    }

    public function wechatUnifiedOrder(array $wxRefundQueryParams)
    {
        $da = new \WxPayUnifiedOrder();
        $da->SetBody($wxRefundQueryParams['body']);
        $da->SetOut_trade_no($wxRefundQueryParams['out_trade_no']);
        $da->SetTotal_fee($wxRefundQueryParams['realTotalMoney']);
        $da->SetSpbill_create_ip($wxRefundQueryParams['spbill_create_ip']);
        $da->SetTime_start($wxRefundQueryParams['time_start']);
        $da->SetNotify_url($wxRefundQueryParams['notify_url']);
        $da->SetTrade_type($wxRefundQueryParams['trade_type']);
        if ($wxRefundQueryParams['trade_type']=='JSAPI') {
            $da->SetOpenid($wxRefundQueryParams['openid']);
        }
        $da->SetLimit_pay('no_credit');
        $da->SetTime_expire($wxRefundQueryParams['time_expire']);
        $mchConfig = $this->getWxMchConfigByAppid($wxRefundQueryParams['appid']);
        new \WxPayConfig($mchConfig);
        $weixin = new \WxPayApi();
        $res = $weixin::unifiedOrder($da);
        return $res;
    }

    /**
     * 检验数据的真实性，并且获取解密后的明文.
     * @param $encryptedData string 加密的用户数据
     * @param $iv string 与用户数据一同返回的初始向量
     * @param $data string 解密后的原文
     *
     * @return int 成功0，失败返回对应的错误码
     */
    public function decryptData($encryptedData, $appid, $sessionKey, $iv)
    {
        if (strlen($sessionKey) != 24) {
            throw AE::factory(AE::WECHAT_MINPROGRAM_LOGIN_ILLEGALAESKEY);

        }
        $aesKey=base64_decode($sessionKey);

        if (strlen($iv) != 24) {
            throw AE::factory(AE::WECHAT_MINPROGRAM_LOGIN_ILLEGALIV);
        }
        $aesIV=base64_decode($iv);

        $aesCipher=base64_decode($encryptedData);

        $result=openssl_decrypt( $aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);

        $dataObj=json_decode( $result );

        if( $dataObj  == NULL )
        {
            throw AE::factory(AE::WECHAT_MINPROGRAM_LOGIN_ILLEGALBUFFER);
        }

        if( $dataObj->watermark->appid != $appid )
        {
            throw AE::factory(AE::WECHAT_MINPROGRAM_APPID_ERROR);
        }

        return $dataObj;
    }
}