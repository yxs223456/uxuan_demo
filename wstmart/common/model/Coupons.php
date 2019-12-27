<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/8/14
 * Time: 14:55
 */

namespace wstmart\common\model;

use think\Db;
use wstmart\common\exception\AppException as AE;

class Coupons extends Base
{
    public function getCouponsUserInfo($where, $field='*')
    {
        $info = Db::name('coupon_users')->where($where)->field($field)->find();
        if (!$info) throw AE::factory(AE::COUPONS_NO_HAVE);
        return $info;
    }

    public function getCouponUserSum($where)
    {
        $info = Db::name('coupon_users')->where($where)->count();
        return $info;
    }

    public function getCouponsInfo($map, $field='*')
    {
        $info = $this->where($map)->field($field)->find();
        if (!$info) throw AE::factory(AE::COUPONS_NO_USE);
        return $info;
    }

    public function getCouponsNovice($map, $field='*')
    {
        $info = $this->where($map)->field($field)->select()->toArray();
        if (!$info) throw AE::factory(AE::COUPONS_NO_USE);
        return $info;
    }

    public function countCouponValueSum($where, $value='couponValue')
    {
        $sum = $this->where($where)->sum($value);
        return $sum;
    }

    public function updateCouponUserUseStatus($couponsId, $userId, $data)
    {
        $status = Db::name('coupon_users')->where(['id'=>$couponsId, 'userId'=>$userId])->update($data);
        if (!$status) throw AE::factory(AE::COUPONS_USE_EXISTS);
        return $status;
    }

    public function getUserCouponData($userId)
    {
        $couponInfo = $this->alias('c')
            ->join('coupon_users cu', 'cu.couponId=c.couponId', 'left')
            ->where('cu.userId',$userId)
            ->where('cu.isUse',0)
            ->where('c.dataFlag',1)
            ->field('c.*')->select()->toArray();
        return $couponInfo;
    }

    public function getNoviceCouponUsersList($userId, $where, $field)
    {
        $couponInfo = $this->alias('c')
            ->join('coupon_users cu', 'cu.couponId=c.couponId', 'left')
            ->where('cu.userId',$userId)
            ->where($where)
            ->field($field)
            ->field('cu.endTime as endDate')->select();
        return $couponInfo;
    }

    public function getUserGroup()
    {
        $userInfo = DB::name('coupon_users')->field('userId')->group('userId')->select();
        return $userInfo;
    }

    public function getCouponIdGroup($where, $map='couponId')
    {
        $info = $this->where($where)->field('couponId')->group($map)->select()->toArray();
        return $info;
    }

    public function getCouponInfoByCouponUserId($couponUserId)
    {
        $info = $this->alias('c')
            ->join('coupon_users cu', 'cu.couponId=c.couponId', 'left')
            ->where('c.couponId', $couponUserId)
            ->field('c.*')
            ->find();
        return $info;
    }

    public function addCouponUserDataAll($data)
    {
        $addNUm = Db::name('coupon_users')->insertAll($data);
        if (!$addNUm) throw AE::factory(AE::COUPONS_GET_FAIL);
        return $addNUm;
    }

    public function updateCouponReceiveNum($couponId, $num=1, $set = true)
    {
        if ($set) {
            $name = 'setInc';
        }else{
            $name = 'setDec';
        }
        return $this->where('couponId', $couponId)->$name('receiveNum', $num);
    }

    /**
     * 获取兑换优惠券的轮播数据
     */
    public function couponCarousel($limit)
    {
        $data = DB::name('coupon_users')->alias('cu')
            ->leftJoin('users u', 'u.userId=cu.userId')
            ->leftJoin('coupons c', 'c.couponId=cu.couponId')
            ->field('cu.createTime,u.nickname,u.userPhoto,u.userPhone,c.couponValue,c.useCondition,c.useMoney,c.type')
            ->order('cu.id', 'desc')
            ->limit(0, $limit)
            ->select();
        return $data;
    }
}