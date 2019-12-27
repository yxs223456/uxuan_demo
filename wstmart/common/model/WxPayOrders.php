<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/8/3
 * Time: 15:13
 */
namespace wstmart\common\model;

use \wstmart\common\exception\AppException as AE;

class WxPayOrders extends Base
{
    public function getWxPayOrderModel(array $map)
    {
        $model = $this->where($map)->find();
        if (empty($model)) {
            throw AE::factory(AE::WECHAT_PAY_ORDERS_NOT_EXISTS);
        }
        return $model;
    }
}