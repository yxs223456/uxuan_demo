<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/10/9
 * Time: 10:52
 */
namespace wstmart\common\model;

class CouponUsers extends Base
{
    public function exchangeList($userId, $offset, $pageSize)
    {
        $query = $this->alias('cu')
            ->leftJoin('coupons c', 'c.couponId=cu.couponId')
            ->where('cu.userId', $userId)
            ->where('c.type', 3);
        $queryClone = clone $query;
        $count = $queryClone->count();
        $list = $query->field('c.couponValue,c.name,c.integral,cu.createTime')
            ->order('cu.id', 'desc')
            ->limit(($offset - 1) * $pageSize, $pageSize)
            ->select()->toArray();
        return [
            'total' => $count,
            'list' => $list
        ];
    }
}