<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/9/29
 * Time: 9:25
 */

namespace wstmart\app\controller;

use wstmart\common\exception\AppException as AE;
use wstmart\common\service\Ads as A;
use wstmart\app\service\Users as ASUser;

class Ads extends Base
{
    protected $beforeActionList = [
        'checkAuth' => ['except'=>'drawadsposition,drawclickrate'],
    ];
    protected $openAction = [
        'drawadsposition',
        'drawclickrate',
    ];

    public function drawAdsPosition()
    {
        $positionCode = getInput('positionCode');
        if (empty($positionCode)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $user = ASUser::getUserByCache();
        $rs = (new A())->drawAdsPosition($positionCode, $user['commonParams']);
        return $this->shopJson($rs);
    }

    public function drawClickRate()
    {
        $adId = (int)getInput('adId');
        if ($adId<1) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $rs = (new A())->drawClickRate($adId);
        return $this->shopJson($rs);
    }
}