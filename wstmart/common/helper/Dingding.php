<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/27
 * Time: 11:33
 */
namespace wstmart\common\helper;

class Dingding
{
    public static function getAccessToken()
    {
        $dingConfig = config('dingding.');
        $corpId = $dingConfig['corpId'];
        $corpSecret = $dingConfig['corpSecret'];
        $url = "https://oapi.dingtalk.com/gettoken?corpid=$corpId&corpsecret=$corpSecret";
        $result = curl($url, 'get', null, false, true);
        if (isset($result['access_token'])) {
            return $result['access_token'];
        }
        return null;
    }

    public static function senMessage($message, $names)
    {
        $accessToken = self::getAccessToken();
        $dingConfig = config('dingding.');
        $agentId = $dingConfig['agentId'];
        $userid_list = '';
        foreach ($names as $name) {
            $userid_list .= $dingConfig['userIds'][$name] . ',';
        }
        $url = 'https://oapi.dingtalk.com/topapi/message/corpconversation/asyncsend_v2?access_token=' . $accessToken;
        $params = [
            'agent_id' => $agentId,
            'userid_list' => $userid_list,
            'to_all_user' => false,
            'msg' => $message,
        ];
        $result = curl($url, 'post', $params, false, true);
        return $result;
    }
}