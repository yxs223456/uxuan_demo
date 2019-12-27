<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 2018-12-13
 * Time: 11:05
 */
namespace wstmart\common\model;

class GoodsActivityGoods extends Base
{
    public function checkGoodsInActivity($goodsId, $activityId)
    {
        $data = $this->where('activityId', $activityId)->where('goodsId', $goodsId)->find();
        return !empty($data);
    }
}