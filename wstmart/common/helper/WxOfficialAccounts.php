<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/15
 * Time: 14:27
 */
namespace wstmart\common\helper;

class WxOfficialAccounts
{
    public function getUserInfoByOpenId($openId, $accessToken)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$accessToken.'&openid='.$openId.'&lang=zh_CN';
        $userInfo = curl($url, 'get', null, false, true);
        return $userInfo;
    }
}