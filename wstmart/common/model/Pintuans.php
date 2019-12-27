<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/6/26
 * Time: 16:44
 */
namespace wstmart\common\model;

use wstmart\common\exception\AppException as AE;
use think\Db;

class Pintuans extends Base
{
    protected $pk = 'tuanId';

    public function getPintuanByTuanId($tuanId)
    {
        $pingtuan = $this->where(['tuanId'=>$tuanId,'tuanStatus'=>1,'dataFlag'=>1])->find();
        if (empty($pingtuan)) {
            throw AE::factory(AE::GOODS_PINTUAN_NOT_EXISTS);
        }
        return $pingtuan;
    }

    public function getPintuanByGoodsId($goodsId)
    {
        $pingtuan = $this->where(['goodsId'=>$goodsId,'dataFlag'=>1,'tuanStatus'=>1])->find();
        return $pingtuan;
    }

    public function getPintuanPriceByTuanId($tuanId)
    {
        $pinTuanPrice = $this->where('tuanId', $tuanId)->value('tuanPrice');
        if (empty($pinTuanPrice)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        return $pinTuanPrice;
    }

    public function getPintuanData($map)
    {
        if (empty($map)) throw AE::factory(AE::COM_PARAMS_ERR);
        $pingtuan = $this->where($map)->value('tuanPrice');
        if (empty($pingtuan)) {
            throw AE::factory(AE::GOODS_PINTUAN_NOT_EXISTS);
        }
        return $pingtuan;
    }

    public function getPintuanOrderByTuanNo($tuanNo)
    {
        $pintuanOrder = DB::name('pintuan_orders')->where('tuanNo', $tuanNo)->find();
        if (empty($pintuanOrder)) {
            throw AE::factory(AE::ORDER_PINTUAN_ORDER_NOT_EXISTS);
        }
        return $pintuanOrder;
    }

    public function getPintuanInfoByOrderId($map)
    {
        $pintuanInfo = DB::name('pintuan_users')->where($map)->find();
        if (empty($pintuanInfo)) {
            throw AE::factory(AE::GOODS_USER_NOT_EXISTS);
        }
        return $pintuanInfo;
    }

    public function getPintuanUserByTuanNo($tuanNo)
    {
        $pintuanInfo = DB::name('pintuan_users')->where('tuanNo', $tuanNo)->select();
        return $pintuanInfo;
    }
}