<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/9/29
 * Time: 9:50
 */

namespace wstmart\common\service;

use wstmart\app\model\Apis;
use wstmart\common\model\Ads as A;
use wstmart\common\helper\Redis;

class Ads
{
    public function drawAdsPosition($positionCode, $commonParams)
    {
        $position = (new Apis())->getDatasType(5);
        $list['total'] = 0;
        $list['list'] = [];
        if (empty($position)) return $list;
        $source = strtolower($commonParams->source);
        $positiontType = $position[$source];
        $redisConfig = config('redis.');
        $r = new Redis($redisConfig);
        $adsRedisName = config('enum.adsPOsitionTypeList');
        $adsKey = $adsRedisName.'_'.$source.'_'.$commonParams->version.'_'.$positionCode;
        $adsData = $r->get($adsKey);
        if (!empty($adsData)) {
            return $adsData;
        }
        $adsInfo = (new A())->drawAdsPositionList($positiontType, $commonParams->version, $positionCode)->toArray();
        if (empty($adsInfo)) return $list;
        foreach ($adsInfo as $k=>$v) {
            $adsInfo[$k]['adFile'] = addImgDomain($v['adFile']);
        }
        $data['total'] = count($adsInfo);
        $data['list'] = $adsInfo;
        $r->set($adsKey, $adsInfo, config('web.adsListExpireDate'));
        return $data;
    }

    public function drawClickRate($adId)
    {
        $adsInfo = (new A())->getAds(['adId'=>$adId]);
        if ($adsInfo) {
            (new A())->setIncAdsClicks($adId);
        }
        return true;
    }
}