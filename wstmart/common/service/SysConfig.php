<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/8/6
 * Time: 13:44
 */

namespace wstmart\common\service;

use wstmart\app\model\SysConfigs as SC;
use wstmart\common\model\SysConfigs as G;
use wstmart\common\model\VersionUps as V;
use wstmart\common\model\SysConfigs;

class SysConfig
{
    public function getGuideInfo($type=1)
    {
        $g = new G();
        $where['dataFlag'] = 1;
        $where['status'] = 1;
        $info = $g->getGuideInfo($where);
        $data = [];
        foreach ($info as $k=>$v) {
            $imgDomain = addImgDomain($v['path']);
            $v['path'] = $imgDomain;
            if ($v['type']==0) {
                $imgInfo = getimagesize($imgDomain);
                $v['width'] = $imgInfo[0];
                $v['height'] = $imgInfo[1];
            }
            if ($v['targetChannel'] == $type || $v['targetChannel'] == 0) {
                unset($v['targetChannel'],$v['dataFlag'],$v['status']);
                $data[$k] = $v;
            }
        }
        return $data;
    }

    public function getGuideImage($type)
    {
        $g = new G();
        $where['dataFlag'] = 1;
        $where['status'] = 1;
        $where['type'] = $type;
        $info = $g->getGuideImage($where);
        if (empty($info)) {
            $data['isUse'] = 0;
            $data['info'] = null;
        } else {
            $data['isUse'] = 1;
            foreach ($info as $k=>$v) {
                $imgDomain = addImgDomain($v['path']);
                $data['info']['imagePath'] = $imgDomain;
                $data['info']['link'] = $v['link'];
                $data['info']['proportion'] = $v['proportion'];
                $imgInfo = getimagesize($imgDomain);
                $data['info']['width'] = $imgInfo[0];
                $data['info']['height'] = $imgInfo[1];
            }
        }
        return $data;
    }

//    public function versionUpgradeReminding($appVersion)
//    {
//        $sc = new SC();
//        $field = ['appVersion', 'upgradeType', 'upgradeUrl', 'upgradeExplain'];
//        $rs = $sc->getSysConfigByField($field);
//        foreach ($rs as $key=>$v) {
//            if ($v['fieldCode']=='appVersion') {
//                if ($v['fieldValue']<=$appVersion) return true;
//            }
//            $result[$v['fieldCode']] = $v['fieldValue'];
//        }
//        $result['upgradeType'] = $result['upgradeType']==1 ? $result['upgradeType'] : 2;
//        return $result;
//    }
    public function versionUpgradeReminding($appVersion,$type)
    {
        $v = new V();
        $where['dataFlag'] = 1;
        $where['type'] = $type=='android' ? 0 : 1;
        $info = $v->getNewestVersion($where);
        if (!$info) {
            $data['isUpgrade'] = 0;
            $data['info'] = null;
        } else {
            if (version_compare($appVersion,$info->versionNumber)===1 || version_compare($appVersion,$info->versionNumber)===0) {
                $data['isUpgrade'] = 0;
                $data['info'] = null;
            } else {
                $data['isUpgrade'] = 1;
                $data['info']['type'] = $info->type==0 ? 'android' : 'ios';
                $data['info']['versionNumber'] = $info->versionNumber;
                $data['info']['upgradeType'] = $info->	upgradeType;
                $data['info']['path'] = addImgDomain($info->path);
                $data['info']['text'] = $info->text;
            }
        }

        return $data;
    }

}