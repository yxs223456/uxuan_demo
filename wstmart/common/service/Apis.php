<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/11
 * Time: 11:58
 */
namespace wstmart\common\service;

use wstmart\common\struct\CommonParams;
use wstmart\common\model\AuditSwitchs;
use wstmart\common\model\SysConfigs;
use wstmart\common\model\Users;
use wstmart\common\model\GoodsTags;
use wstmart\app\service\Users as ASUsers;
use wstmart\common\model\UserNews;


class Apis
{
    public function isHide(CommonParams $commonParams)
    {
        $auditSwitch = (new SysConfigs())->getSysConfig(['fieldCode'=>'auditSwitch']);
        if ($auditSwitch != 1) {
            return false;
        }
        $switch = (new AuditSwitchs())->getAuditSwitchInfo([
            'channel' => $commonParams->channel,
            'version' => $commonParams->version,
            'dataFlag' => 1,
        ]);
        if (!$switch) {
            return false;
        }
        return true;
    }


    public function getUserGoodsTags($userId)
    {
        $allTags = (GoodsTags::all(['dataFlag'=>1, 'isShow'=>1]))->toArray();
        $weight = array_column($allTags, 'weight');
        array_multisort($weight, SORT_ASC, $allTags);
        $user = Users::get(['userId'=>$userId]);
        $userTags = explode(',', $user->favoriteGoodsTags);
        $rs = [];
        foreach ($allTags as $key => $tag) {
            $isFavorite = in_array($tag['id'], $userTags);
            $rs[] = [
                'id' => $tag['id'],
                'name' => $tag['name'],
                'isFavorite' => $isFavorite,
            ];
        }
        return $rs;
    }

    public function indexConfigInfo()
    {
        $userId = ASUsers::getUserByCache()['userId'];
        $userNews = new UserNews();
        $where['userId'] = $userId;
        $userInfo = (new Users())->getUserInfo($where);
        $newsType = config('web.newsType');
        $total = [];
        foreach ($newsType as $k=>$v) {
            $where['newsType'] = $v;
            $total[$k] = $userNews->getNewsList($where, 'title', 'createTime', true)['isReadTotal'];
        }
        $data['newsTotal'] = array_sum($total)<100 ? array_sum($total) : 99;
        if (!empty($userInfo->inviter)) {
            $data['Apprentice']['status'] = 1;
            $data['Apprentice']['reward'] = 200;
            $inviterInfo = (new Users())->getUserInfo(['userId'=>$userInfo->inviter]);
            $data['Apprentice']['inviterUser'] = $inviterInfo->nickname ?? $inviterInfo->userName;
        }else{
            $data['Apprentice']['status'] = 0;
            $data['Apprentice']['reward'] = 0;
            $data['Apprentice']['inviterUser'] = '';
        }
        return $data;
    }


}