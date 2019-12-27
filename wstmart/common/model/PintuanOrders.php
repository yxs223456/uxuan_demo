<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/14
 * Time: 14:22
 */
namespace wstmart\common\model;

use wstmart\common\exception\AppException as AE;

class PintuanOrders extends Base
{
    public function getPintuanOrderByTuanNo($tuanNo)
    {
        $pintuanOrder = $this->where('tuanNo', $tuanNo)->find();
        if (empty($pintuanOrder)) {
            throw AE::factory(AE::ORDER_PINTUAN_ORDER_NOT_EXISTS);
        }
        return $pintuanOrder;
    }
}