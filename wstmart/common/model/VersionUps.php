<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/9/10
 * Time: 16:24
 */

namespace wstmart\common\model;

use wstmart\common\exception\AppException as AE;


class VersionUps extends Base
{
    public function getInfo($where)
    {
        $rs = $this->where($where)->find();
        return $rs;
    }

    public function getNewestVersion($where)
    {
        $info = $this->where($where)->column('versionNumber','id');
        if (!$info) return null;
        $maxValueId = array_search(max($info),$info);
        $info = $this->getInfo(['id'=>$maxValueId]);
        return $info;
    }
}