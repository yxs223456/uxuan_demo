<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/8/8
 * Time: 16:32
 */

namespace wstmart\common\service;

use wstmart\common\model\GoodsAppraises as M;
use wstmart\app\service\Users as ASUsers;
use wstmart\common\model\Goods as MGoods;
use wstmart\common\exception\AppException as AE;
use think\Db;

class GoodsAppraises
{

    /*
     * 评论列表
     */
    public function goodsAppraiseList($goodsId, $offset, $pageSize)
    {
        $goodsModel = new MGoods();
        $data['avgScore'] = $goodsModel->goodsAveScore($goodsId);
        $data['total'] = $goodsModel->goodsAppraiseNum($goodsId);
        $m = new M();
        $appraiseList = $m->getAppraiseList($goodsId, $offset, $pageSize);
        foreach($appraiseList as $k=>$v){
            $images = explode(',', $v['images']);
            $img = '';
            foreach ($images as $item) {
                if (empty($item)) continue;
                $img .= addImgDomain($item).',';
            }
            if (substr($img,-1) === ',') $img = substr($img, 0, -1);
            $appraiseList[$k]['images'] = $img;
            $appraiseList[$k]['userPhoto'] = addImgDomain($v['userPhoto']);
            $appraiseList[$k]['nickname'] = $appraiseList[$k]['nickname']!=='' ? $appraiseList[$k]['nickname'] : hideUserPhone($appraiseList[$k]['userPhone']);
            unset($appraiseList[$k]['userPhone']);
        }
        $data['list'] = $appraiseList;
        $data['offset'] = $offset;
        $data['pagesize'] = $pageSize;
        return $data;
    }

    public function goodsAppraisesInfo($apprId)
    {
        $m = new M();
        $arrInfo = $m->apprinfo($apprId);
        $images = explode(',', $arrInfo['images']);
        $img = '';
        foreach ($images as $item) {
            $img .= addImgDomain($item).',';
        }
        $arrInfo['images'] = $img;
        return $arrInfo;
    }

    public function insertGoodsAppraises($userId, $orderNo, $goodsScore, $timeScore, $content, $images)
    {
        $m = new M();
        $orderGoodsInfo = model('common/Orders')->getOrderAndGoodsInfo(['orderNo'=>$orderNo, 'userId'=>$userId, 'dataFlag'=>1]);
        if(empty($orderGoodsInfo))throw AE::factory(AE::GOODS_APPRAISES_INVALID);
        if ($orderGoodsInfo['isAppraise']==1) {
            throw AE::factory(AE::GOODS_APPRAISES_EXISTS);
        }
        if ($orderGoodsInfo['orderStatus']!=2) throw AE::factory(AE::GOODS_APPRAISES_EXCHANGE);
        $serviceScore = 0;
        //检测商品是否已评价
        Db::startTrans();
        try{
            $rs = $m->insertGoodsAppraise($userId, $orderGoodsInfo, $goodsScore, $timeScore, $content, $images);
            if($rs ==false) throw AE::factory(AE::GOODSAPPRAISES_INSERT_FAIL);
             //WSTUseImages(0, $this->id, $data['images']);
            //添加其他评分
            $m->insertGoodsAppraiseNum($orderGoodsInfo, $goodsScore, $serviceScore, $timeScore);
            // 查询该订单是否已经完成评价,修改orders表中的isAppraise
            //如果有积分需要添加
            model('orders')->where('orderId',$orderGoodsInfo['orderId'])->update(['isAppraise'=>1,'isClosed'=>1]);
            Db::commit();
            return true;
        }catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }
}