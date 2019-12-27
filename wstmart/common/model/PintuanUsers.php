<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/6
 * Time: 16:35
 */
namespace wstmart\common\model;

use wstmart\common\exception\AppException as AE;

class PintuanUsers extends Base
{
    public function getPintuanUserByTuanNoAndUserId($tuanNo, $userId)
    {
        $pintuanUser = $this
            ->where('tuanNo', $tuanNo)
            ->where('userId', $userId)
            ->where('dataFlag', 1)
            ->find();
        return $pintuanUser;
    }

    public function getPintuanUserByOrderId($orderId)
    {
        $pintuanUser = $this
            ->where('orderId', $orderId)
            ->find();
        if (empty($pintuanUser)) {
            throw AE::factory(AE::ORDER_PINTUAN_USER_NOT_EXISTS);
        }
        return $pintuanUser;
    }

    public function getPintuanUserByOrderNo($orderNo)
    {
        $pintuanUser = $this
            ->where('orderNo', $orderNo)
            ->find();
        if (empty($pintuanUser)) {
            throw AE::factory(AE::ORDER_PINTUAN_USER_NOT_EXISTS);
        }
        return $pintuanUser;
    }

    public function getPintuanUserPhotoByTuanNo($tuanNo)
    {
        $pintuanUsers = $this->alias('pu')
            ->leftJoin('users u', 'u.userId=pu.userId')
            ->where('pu.tuanNo', $tuanNo)
            ->where('pu.dataFlag', 1)
            ->order('pu.isHead', 'desc')
            ->column('u.userPhoto');
        if (!is_array($pintuanUsers)) {
            return [];
        }
        foreach ($pintuanUsers as &$userPhoto) {
            $userPhoto = addImgDomain($userPhoto);
        }
        unset($userPhoto);
        return $pintuanUsers;
    }

    public function getPintuanUsersByTuanNo($tuanNo)
    {
        $pintuanUsers = $this
            ->where('tuanNo', $tuanNo)
            ->where('dataFlag', 1)
            ->order('isHead', 'desc')
            ->select();
        return $pintuanUsers;
    }

    public function getHeadUserByTuanNo($tuanNo)
    {
        $headUser = $this
            ->where('tuanNo', $tuanNo)
            ->where('isHead', 1)
            ->where('dataFlag', 1)
            ->find();
        if (empty($headUser)) {
            throw AE::factory(AE::ORDER_PINTUAN_HEAD_NOT_EXISTS);
        }
        return $headUser;
    }
}