<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/9/14
 * Time: 15:53
 */

namespace wstmart\common\service;

use wstmart\common\model\Users as U;
use wstmart\common\model\Coupons as C;
use wstmart\common\model\CouponUsers as CMCouponUsers;
use wstmart\common\exception\AppException as AE;
use think\Db;
use wstmart\common\model\Goods;
use wstmart\common\service\Apis;
use wstmart\common\struct\CommonParams;
use wstmart\common\model\GoodsCats as GC;


class Coupons
{
    public function sendFanliCoupons($unionId, $userId)
    {
        $couponIds = DB::name('fanli_send_coupons')
            ->where('unionId', $unionId)->where('isSend', 0)->column('couponId', 'id');
        foreach ($couponIds as $id=>$couponId) {
            DB::startTrans();
            try {
                DB::name('fanli_send_coupons')
                    ->where('id', $id)
                    ->update(['isSend'=>1, 'sendTime'=>date('Y-m-d H:i:s')]);
                $couponInfo = [
                    'shopId' => 1,
                    'couponId' => $couponId,
                    'userId' => $userId,
                    'isUse' => 0,
                    'createTime' => date('Y-m-d H:i:s'),
                    'endTime' => date('Y-m-d', strtotime('+30 day')),
                ];
                DB::name('coupon_users')->insert($couponInfo);
                DB::name('coupons')->where('couponId', $couponId)->setInc('receiveNum', 1);
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollback();
                throw $e;
            }
        }
    }

    public function sendLotteryCoupons($unionId, $userId)
    {
        $couponIds = DB::name('lottery_send_coupons')
            ->where('unionId', $unionId)->where('isSend', 0)->column('couponId', 'id');
        foreach ($couponIds as $id=>$couponId) {
            DB::startTrans();
            try {
                DB::name('lottery_send_coupons')
                    ->where('id', $id)
                    ->update(['isSend'=>1, 'sendTime'=>date('Y-m-d H:i:s')]);
                $couponInfo = [
                    'shopId' => 1,
                    'couponId' => $couponId,
                    'userId' => $userId,
                    'isUse' => 0,
                    'createTime' => date('Y-m-d H:i:s'),
                    'endTime' => date('Y-m-d', strtotime('+30 day')),
                ];
                DB::name('coupon_users')->insert($couponInfo);
                DB::name('coupons')->where('couponId', $couponId)->setInc('receiveNum', 1);
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollback();
                throw $e;
            }
        }
    }

    public function noviceGiftBag($userId)
    {
        $userCreateTime = (new U())->getUserInfo(['userId'=>$userId], 'createTime, isNovice');
        if ($userCreateTime->isNovice==0) {
            $data = $this->publicCouponsList(true);
            $data['total'] = count($data['list']);
        } else {
            $data = [
                'total'=>0,
                'list'=>[]
            ];
        }
        return $data;
    }

    public function receiveNoviceGiftBag($couponIds, $userId)
    {
        $couponIds = json_decode($couponIds, true);
        if (!is_array($couponIds)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $c  = new C();
        $u = new U();
        $userinfo = $u->getUserInfo(['userId'=>$userId]);
        if ($userinfo->isNovice!=0) throw AE::factory(AE::COUPONS_NEW_CAN_RECEIVE);
        foreach ($couponIds as $k=>$v) {
           $couponShopId = $c->getCouponsInfo(['couponId'=>$v], 'shopId, type, limitNum, dataFlag');
            if ($couponShopId->type!=1 || $couponShopId->dataFlag==-1) continue;
           $couponUserSum = $c->getCouponUserSum(['couponId'=>$v, 'userId'=>$userId]);
           if ($couponShopId->limitNum<=$couponUserSum) throw AE::factory(AE::COUPONS_NUM_TOMAX);
           $couponData[$k] = [
               'shopId'=>$couponShopId->shopId,
               'couponId'=>$v,
               'userId'=>$userId,
               'createTime'=>date('Y-m-d H:i:s'),
               'endTime'=>date('Y-m-d H:i:s', time()+config('web.noviceCouponExpireDate'))
           ];
        }
        Db::startTrans();
        try{
            $c->addCouponUserDataAll($couponData);
            foreach ($couponIds as $item => $id) {
                $c->updateCouponReceiveNum($id);
            }
            $u->updateUserInfo(['userId'=>$userId], ['isNovice'=>1]);
            $data = $this->publicCouponsList(false, $userId);
            $data['status'] = true;
            Db::commit();
            return $data;
        }catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function couponGoodsList($couponId, $userId, $commonParams, $offset=1, $pagesize=10)
    {
        $m = new C();
        $couponsUserInfo = $m->getCouponsUserInfo(['userId'=>$userId, 'couponId'=>$couponId]);
        $couponInfo = $m->getCouponsInfo(['couponId'=>$couponId], 'useObjects, useObjectIds, dataFlag');
        if (date('Y-m-d') > $couponsUserInfo['endTime']) throw AE::factory(AE::COUPONS_DATE_EXPIRE);
        if ($couponInfo->useObjects!=1) throw AE::factory(AE::COUPONS_NO_USEOBJECTIDS);
        $goods = explode(',', $couponInfo->useObjectIds);
        $c = new Goods();
        $isHide = (new Apis())->isHide($commonParams);
        $list = [];
        foreach ($goods as $k=>$v) {
            $goodsInfo = $c->getGoodsById($v);
            $goodsCatIsAudit = $this->selectGoodsCatsIsAudit($goodsInfo->goodsCatIdPath, 0);
            if ($isHide==true) {
                if ($goodsCatIsAudit==1 || $goodsInfo->isAudit==1) continue;
            }
            if ($goodsInfo->dataFlag!=1) continue;
            $list[$k]['goodsId'] = $goodsInfo->goodsId;
            $list[$k]['goodsName'] = $goodsInfo->goodsName;
            $list[$k]['goodsImg'] = addImgDomain($goodsInfo->goodsImg);
            $list[$k]['favoriteNum'] = $goodsInfo->favoriteNum;
            $list[$k]['shareNum'] = $goodsInfo->shareNum;
            $list[$k]['shopPrice'] = $goodsInfo->shopPrice;
            $list[$k]['isFavorite'] = $c->checkIsFavorite($v);
        }
        $data['total'] = count($list);
        $data['list'] = array_slice($list, ($offset-1)*$pagesize, $pagesize-1);
        $data['offset'] = $offset;
        $data['pagesize'] = $pagesize;
        return $data;
    }

    public function publicCouponsList($isShowCouponIds= true, $userId=null)
    {
        $where['type'] = 1;
        $where['dataFlag'] = 1;
        $c = new C();
        $sum = $c->countCouponValueSum($where) ?? 0;
        if($isShowCouponIds){
            if ($sum>0) {
                $list['sum'] = $sum;
                $list['list'] = $c->getCouponsNovice($where, 'couponId, name, couponValue, useCondition, useMoney, useObjects');
                $groupIds = $c->getCouponIdGroup($where);
                foreach ($groupIds as $k=>$v)
                {
                    $couponIds[] = $v['couponId'];
                }
                $list['couponIds'] = json_encode($couponIds);
            } else {
                $list['sum'] = 0;
                $list['list'] = [];
                $list['couponIds'] = '';
            }
        } else {
            $map['c.type'] = 1;
            $map['c.dataFlag'] = 1;
            $list['sum'] = $sum;
            $list['list'] = $c->getNoviceCouponUsersList($userId, $map, 'c.couponId, c.name, c.couponValue, c.useCondition, c.useMoney, c.useObjects');
        }
        return $list;
    }

    public function selectGoodsCatsIsAudit($cat, $path=0)
    {
        $cats = explode('_', $cat);
        $gc = new GC();
        $goodsCatsInfo = $gc->getGoodsCatInfo(['catId'=>$cats[0], 'dataFlag'=>1], 'isAudit');
        return $goodsCatsInfo[0]['isAudit'];
    }

    public function exchangeList($userId, $offset, $pageSize)
    {
        $couponUsersModel = new CMCouponUsers();
        $list = $couponUsersModel->exchangeList($userId, $offset, $pageSize);

        $exchangeList = [];
        foreach ($list['list'] as $item) {
            $timeStamp = strtotime($item['createTime']);
            $date = date('Y-m', $timeStamp);
            $month = date('n', $timeStamp);
            if (!isset($exchangeList[$date])) {
                $exchangeList[$date]['date'] = $date;
                $exchangeList[$date]['month'] = $month . '月';
            }
            $exchangeList[$date]['list'][] = [
                'name' => $item['name'],
                'couponValue' => $item['couponValue'],
                'score' => $item['integral'],
                'time' => date('Y-m-d H:i:s', $timeStamp),
            ];
        }
        $exchangeList = array_values($exchangeList);
        return [
            'total' => $list['total'],
            'exchangeList' => $exchangeList,
            'offset' => $offset,
            'pageSize' => $pageSize,
        ];
    }
}