<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/3
 * Time: 11:31
 */
namespace wstmart\common\helper;

use wstmart\common\exception\AppException as AE;

class WxTemplateNotify
{
    //小程序
    public function mpSend($accessToken, $openId, $templateId, $formId, $data, $page=null, $emphasisKeyword=null)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=' . $accessToken;
        $postParams = [
            'touser' => $openId,
            'template_id' => $templateId,
            'form_id' => $formId,
            'data' => $data,
        ];
        if ($page !== null) {
            $postParams['page'] = $page;
        }
        if ($emphasisKeyword !== null) {
            $postParams['emphasis_keyword'] = $emphasisKeyword;
        }
        $sendResult = curl($url, 'post', $postParams, true);
        return $sendResult;
    }

    /**
     *发送微信模板
     * @data 模板内容
     * @appid 公众号或者小程序appid
     */
    public function sendWeixinTemplate($data,$appid)
    {
        $accessToken = getUxuanAccessToken($appid);
        $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$accessToken;
        $rs = curl($url,'post',$data,true);
        return $rs;
    }

    /**
     *过滤微信消息模板
     * @msg array 消息内容
     * @openId 用户唯一标识
     * @url 跳转url
     * @template_id 模板id
     * @$miniprogramAppid 小程序id
     * @pagepath 所需跳转到小程序的具体页面路径，支持带参数,（示例index?foo=bar）
     */
    public function filterWeixinTemplate($msg, $remark,$openId,$url=null,$template_id,$miniprogramAppid=null,$pagepath=null,$color='#173177')
    {
        if (empty($msg) || empty($openId)) {
            throw AE::factory(AE::WECHAT_TEMPLATE_PARAMS_EMPTY);
        }
        $data['touser'] = $openId;
        $data['template_id'] = $template_id;
        if ($url) {
            $data['url'] = $url;
        }
        if ($miniprogramAppid) {
            $data['miniprogram']['appid'] = $miniprogramAppid;
            if ($pagepath) $data['miniprogram']['pagepath'] = $pagepath;
        }
        $templateArray = config('weixin.templateArray');
        foreach ($msg as $k=>$v) {
            if (in_array($template_id, $templateArray)) {
                $data['data'][$k] = ['value'=>$v,'color'=>$color];
            } else {
                if ($k=='first') {
                    $data['data']['first'] = ['value'=>$v,'color'=>$color];
                } else {
                    $data['data']['keyword'.$k] = ['value'=>$v,'color'=>$color];
                }
            }
        }
        if ($remark)  $data['data']['remark'] = ['value'=>$remark,'color'=>$color];
        return $data;
    }
}