<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/9/10
 * Time: 15:43
 */

namespace wstmart\common\model;


class AuditSwitchs extends Base
{
    public function getAuditSwitchInfo($where)
    {
        $rs = $this->where($where)->find();
        return $rs;
    }

    public function getAuditSwitchselect()
    {
        $rs = $this->where('dataFlag', 1)->select();
        return $rs;
    }
}