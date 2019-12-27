<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/25
 * Time: 13:25
 */
namespace wstmart\common\helper;

include_once(\Env::get('root_path') . 'extend/alidayu/vendor/autoload.php');
use Aliyun\Core\Config;
use Aliyun\Core\Profile\DefaultProfile;
use Aliyun\Core\DefaultAcsClient;
use Aliyun\Api\Sms\Request\V20170525\SendSmsRequest;

// 加载区域结点配置
Config::load();
class Sms
{
    static $acsClient = null;

    /**
     * 取得AcsClient
     *
     * @return DefaultAcsClient
     */
    public static function getAcsClient() {
        //产品名称:云通信流量服务API产品,开发者无需替换
        $product = "Dysmsapi";

        //产品域名,开发者无需替换
        $domain = "dysmsapi.aliyuncs.com";

        $alidayuConfig = config('sms.alidayu');
        $accessKeyId = $alidayuConfig['accessKeyId']; // AccessKeyId

        $accessKeySecret = $alidayuConfig['accessKeySecret']; // AccessKeySecret

        // 暂时不支持多Region
        $region = "cn-hangzhou";

        // 服务结点
        $endPointName = "cn-hangzhou";


        if(static::$acsClient == null) {

            //初始化acsClient,暂不支持region化
            $profile = DefaultProfile::getProfile($region, $accessKeyId, $accessKeySecret);

            // 增加服务结点
            DefaultProfile::addEndpoint($endPointName, $region, $product, $domain);

            // 初始化AcsClient用于发起请求
            static::$acsClient = new DefaultAcsClient($profile);
        }
        return static::$acsClient;
    }
    /**
     * 发送短信
     * @return stdClass
     */
    public static function sendSms($mobile,$template, $param, $sign) {

        // 初始化SendSmsRequest实例用于设置发送短信的参数
        $request = new SendSmsRequest();

        //可选-启用https协议
        //$request->setProtocol("https");

        // 必填，设置短信接收号码
        $request->setPhoneNumbers($mobile);

        // 必填，设置签名名称，应严格按"签名名称"填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
        $request->setSignName($sign);

        // 必填，设置模板CODE，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
        $request->setTemplateCode($template);

        // 可选，设置模板参数, 假如模板中存在变量需要替换则为必填项
        $request->setTemplateParam($param);  // 短信模板中字段的值

        // 选填，上行短信扩展码（扩展码字段控制在7位或以下，无特殊需求用户请忽略此字段）
        $request->setSmsUpExtendCode("1234567");

        // 发起访问请求
        $acsResponse = static::getAcsClient()->getAcsResponse($request);
        if (!isset($acsResponse->Code) || $acsResponse->Code !== 'OK') {
            $message = '【每日优选】' . date('Y-m-d H:i:s') . '||短信发送失败||手机号' . $mobile . '||阿里云返回：' . json_encode($acsResponse, JSON_UNESCAPED_UNICODE);
            $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
            $names = ['杨秀山'];
            Dingding::senMessage($dingMessage, $names);
        }
        return $acsResponse;
    }
}