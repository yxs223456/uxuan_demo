<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/9/11
 * Time: 16:04
 */

namespace wstmart\admin\model;

use think\Db;
use wstmart\common\model\Goods;

class Coupons extends Base
{
    public function pageQuery($type=1)
    {
        return $this->alias('c')
            ->join('shops s', 'c.shopId=s.shopId', 'left')
            ->field('c.*,s.shopName')
            ->where(['c.dataFlag'=>1, 'type'=>$type])->order('createTime asc')->paginate(input('limit/d'));
    }

    /**
     * 根据ID获取
     */
    public function getById($couponId)
    {
        $obj = null;
        if($couponId>0){
            $obj = $this->get(['couponId'=>$couponId,"dataFlag"=>1]);
        }
        return $obj;
    }

    /**
     * 根据ID获取
     */
    public function getGoodsInfoById($couponId)
    {
        $obj = null;
        if($couponId>0){
            $obj = $this->get(['couponId'=>$couponId,"dataFlag"=>1]);
            if ($obj->useObjects==1) {
                $goodsId = explode(',', $obj->useObjectIds);
                foreach ($goodsId as $k=>$v) {
                    $data[$k] = (new Goods())->getGoodsById($v);
                }
            } else {
                $data = [];
            }
        } else {
            $data = [];
        }
        return $data;
    }

    public function add($data)
    {
        $data['startDate'] = date('Y-m-d');
        $data['endDate'] = date('Y-m-d',time()+config('web.noviceCouponExpireDate'));
        $data['limitNum'] = 1;
        $data['couponNum'] = 0;
        $data['type'] = 1;
        $data['createTime'] = date('Y-m-d H:i:s');;
        if ($data['useObjects']==1) {
            $goodsIds = explode(',',$data['useObjectIds']);
            $goods = Db::name('goods')->where([['goodsId','in',$goodsIds],['shopId','=',$data['shopId']],['isSale','=',1],['goodsStatus','=',1],['dataFlag','=',1]])
                ->field('goodsId,goodsCatIdPath')->select();
            if(empty($goods))return WSTReturn('请选择优惠券适用的商品');
        } else {
            $data['useObjectIds'] = '';
        }
        $result = $this
            ->allowField(['name', 'shopId','couponValue','useCondition','useMoney',
                'startDate','endDate','couponNum','limitNum','useObjects', 'type',
                'useObjectIds', 'grantObjects', 'grantObjectIds', 'dataFlag', 'createTime'])
            ->save($data);
        if(false !== $result){
            return WSTReturn("新增成功", 1);
        }else{
            return WSTReturn($this->getError(),-1);
        }
    }

    public function edit($data)
    {
        $id = $data['couponId'];
        $data['limitNum'] = 1;
        $data['couponNum'] = 0;
       if ($data['useObjects']==1) {
            $goodsIds = explode(',',$data['useObjectIds']);
            $goods = Db::name('goods')->where([['goodsId','in',$goodsIds],['shopId','=',$data['shopId']],['isSale','=',1],['goodsStatus','=',1],['dataFlag','=',1]])
                ->field('goodsId,goodsCatIdPath')->select();
            if(empty($goods))return WSTReturn('请选择优惠券适用的商品');
        } else {
            $data['useObjectIds'] = '';
        }
        WSTUnset($data,'createTime');
        $this->allowField(true)->save($data,['couponId'=>$id]);
        return WSTReturn("编辑成功", 1);
    }

    public function getCouponInfo($where)
    {
        $info = $this->where($where)->order('createTime asc')->select()->toArray();
        return $info;
    }
    /**
     * 删除
     */
    public function del($id){
        $data['dataFlag'] = -1;
        $rs = $this->where('couponId', $id)->update($data);
        if ($rs==1) {
            return WSTReturn("刪除成功", 1);
        } else {
            return WSTReturn("刪除失敗", -1);
        }
    }

    public function exchangeAdd($data)
    {
        $data['limitNum'] = 0;
        $data['couponNum'] = 0;
        $data['type'] = 3;
        $data['createTime'] = date('Y-m-d H:i:s');;
        if ($data['useObjects']==1) {
            $goodsIds = explode(',',$data['useObjectIds']);
            $goods = Db::name('goods')->where([['goodsId','in',$goodsIds],['shopId','=',$data['shopId']],['isSale','=',1],['goodsStatus','=',1],['dataFlag','=',1]])
                ->field('goodsId,goodsCatIdPath')->select();
            if(empty($goods))return WSTReturn('请选择优惠券适用的商品');
        } else {
            $data['useObjectIds'] = '';
        }
        $result = $this
            ->allowField(['name', 'shopId','couponValue','useCondition','useMoney',
                'startDate','endDate','couponNum','limitNum','useObjects', 'type',
                'useObjectIds', 'isDailyLimit', 'dailyLimitNum', 'integral', 'dataFlag', 'createTime'])
            ->save($data);
        if(false !== $result){
            return WSTReturn("新增成功", 1);
        }else{
            return WSTReturn($this->getError(),-1);
        }
    }

    public function exchangeEdit($data)
    {
        $id = $data['couponId'];
        $data['limitNum'] = 0;
        $data['couponNum'] = 0;
        if ($data['useObjects']==1) {
            $goodsIds = explode(',',$data['useObjectIds']);
            $goods = Db::name('goods')->where([['goodsId','in',$goodsIds],['shopId','=',$data['shopId']],['isSale','=',1],['goodsStatus','=',1],['dataFlag','=',1]])
                ->field('goodsId,goodsCatIdPath')->select();
            if(empty($goods))return WSTReturn('请选择优惠券适用的商品');
        } else {
            $data['useObjectIds'] = '';
        }
        WSTUnset($data,'createTime');
        $this->allowField(true)->save($data,['couponId'=>$id]);
        return WSTReturn("编辑成功", 1);
    }
}