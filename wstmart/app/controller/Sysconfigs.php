<?php
/**
 * Created by PhpStorm.
 * User: cr6
 * Date: 2018/6/21
 * Time: 14:02
 */

namespace wstmart\app\controller;
use wstmart\common\service\SysConfig as S;
use wstmart\common\exception\AppException as AE;
use wstmart\app\service\Users as ASUser;

class Sysconfigs extends Base
{
    //可以访问的方法
    protected $openAction = [
        'getguideimage',
        'versionupgradereminding'
    ];

    /**
     * 获取导航页视频和图片
     */
    public function getGuideInfo(){
        $s = new S();
        $type = getInput('type');
        $msg = $s->getGuideInfo($type);
        return $this->shopJson($msg);
    }

    /**
     * 版本升级
     */
    public function versionUpgradeReminding(){
        $publicParams= ASUser::getUserByCache()['commonParams'];
        $appVersion = $publicParams->version;
        $type = $publicParams->system;
        if (empty($appVersion) || empty($type)) throw AE::factory(AE::COM_PARAMS_EMPTY);
        $s = new S();
        $msg = $s->versionUpgradeReminding($appVersion,$type);
        return $this->shopJson($msg);
    }

    /**
     * 获取导航页视频和图片
     */
    public function getGuideImage(){
        $s = new S();
        $type = getInput('type');
        $msg = $s->getGuideImage($type);
        return $this->shopJson($msg);
    }
}