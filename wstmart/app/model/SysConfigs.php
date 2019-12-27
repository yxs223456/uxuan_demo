<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/6/21
 * Time: 14:09
 */

namespace wstmart\app\model;
use wstmart\admin\model\SysConfigs as S;
use wstmart\common\exception\AppException as AE;

class SysConfigs extends Base
{

    public function getSysConfigByField($field)
    {
        $rs = $this->field('fieldCode,fieldValue')
            ->whereIn('fieldCode',  $field)->select();
        if (!$rs) throw AE::factory(AE::DATA_GET_FAIL);
        return $rs;
    }

}