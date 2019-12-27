<?php
namespace wstmart\common\model;

use think\Db;
use wstmart\common\helper\Redis;
use wstmart\common\exception\AppException as AE;
use wstmart\app\service\Users as ASUsers;
use Config;

/**
 * 商品类
 */
class Goods extends Base{
	protected $pk = 'goodsId';

	public function getGoodsById($goodsId)
    {
        $goods = $this->where(['goodsId'=>$goodsId])->find();
        if (!$goods) {
            throw AE::factory(AE::GOODS_NOT_EXISTS);
        }
        return $goods;
    }

    public function checkIsFavorite($goodsId)
    {
        $userId = ASUsers::getUserByCache()['userId'];
        if (empty($userId)) {
            return false;
        }
        $favoriteInfo = DB::name('goods_favorites')
            ->where('userId', $userId)
            ->where('goodsId', $goodsId)
            ->where('isDelete', 0)
            ->find();
        if (empty($favoriteInfo)) {
            return false;
        }
        return true;
    }

    public function addFavorite($goodsId, $userId)
    {
         if (empty($userId)) {
            throw AE::factory(AE::USER_NOT_LOGIN);
         }
         $isFavorite = $this->checkIsFavorite($goodsId);
         if ($isFavorite == false) {
             DB::startTrans();
             try {
                 $this->where('goodsId', $goodsId)
                     ->inc('favoriteNum', 1)
                     ->update();
                 $favoriteNum = $this->where('goodsId', $goodsId)->value('favoriteNum');
                 $this->cacheGoodsFavoriteNum($goodsId, $favoriteNum);
                 DB::name('goods_favorites')->insert([
                     'userId' => $userId,
                     'goodsId' => $goodsId,
                     'isDelete' => 0,
                 ]);
                 DB::commit();
             } catch (\Throwable $e) {
                 DB::rollback();
                 throw $e;
             }
         }
    }

    public function cancelFavorite($goodsId)
    {
        $userId = ASUsers::getUserByCache()['userId'];
        if (empty($userId)) {
            throw AE::factory(AE::USER_NOT_LOGIN);
        }
        $isFavorite = $this->checkIsFavorite($goodsId);
        if ($isFavorite == true) {
            DB::startTrans();
            try {
                $this->where('goodsId', $goodsId)
                    ->dec('favoriteNum', 1)
                    ->update();
                $favoriteNum = $this->where('goodsId', $goodsId)->value('favoriteNum');
                $this->cacheGoodsFavoriteNum($goodsId, $favoriteNum);
                DB::name('goods_favorites')
                    ->where([
                    'userId' => $userId,
                    'goodsId' => $goodsId,])
                    ->useSoftDelete('isDelete', 1)
                    ->delete();
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollback();
                throw $e;
            }
        }
    }

    protected function cacheGoodsFavoriteNum($goodsId, $favoriteNum)
    {
        $redisConfig = config('redis.');
        $redis = new Redis($redisConfig);
        $redis->set('goods_favorite_num_' . $goodsId, $favoriteNum);
    }

    public function addShare($goodsId)
    {
        DB::startTrans();
        try {
            $userId = ASUsers::getUserByCache()['userId']??0;
            $this->where('goodsId', $goodsId)
                ->inc('shareNum', 1)
                ->update();
            $shareNum = $this->where('goodsId', $goodsId)->value('shareNum');
            $this->cacheGoodsShareNum($goodsId, $shareNum);
            DB::name('goods_share')->insert([
                'userId' => $userId,
                'goodsId' => $goodsId,
            ]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }
    }

    protected function cacheGoodsShareNum($goodsId, $shareNum)
    {
        $redisConfig = config('redis.');
        $redis = new Redis($redisConfig);
        $redis->set('goods_share_num_' . $goodsId, $shareNum);
    }

    public function favoriteList($userId, $offset, $pageSize)
    {
        $db = DB::name('goods_favorites')->alias('gf')
            ->rightJoin('goods g', 'gf.goodsId=g.goodsId')
            ->where('gf.userId', $userId)
            ->where('gf.isDelete', 0);
        $db1= clone $db;
        $count = $db1->count();
        $list = $db->order('gf.id', 'desc')
            ->limit(($offset - 1) * $pageSize, $pageSize)
            ->field('g.goodsId,g.goodsName,g.goodsImg,g.littleDesc,g.marketPrice,g.shopPrice')
            ->select();
        foreach ($list as &$item) {
            $item['goodsImg'] = addImgDomain($item['goodsImg']);
        }
        return [
            'total' => $count,
            'list' => $list,
            'offset' => $offset,
            'pageSize' => $pageSize
        ];
    }

    /**
     * 商品综合评分
     */
    public function goodsAveScore($goodsId)
    {
        $scoreInfo = DB::name('goods_scores')
            ->where('goodsId', $goodsId)
            ->find();
        do {
            if (!config('web.temp_appraises_open')) {
                if (!$scoreInfo || $scoreInfo['goodsUsers'] == 0) return '0.0';
                return bcdiv($scoreInfo['goodsScore'], $scoreInfo['goodsUsers'], 1);
            }
            $tempScoreInfo = DB::name('goods_temp_scores')
                ->where('goodsId', $goodsId)
                ->find();
            if (!$scoreInfo && !$tempScoreInfo) return '0.0';
            if (!$scoreInfo && $tempScoreInfo && $tempScoreInfo['goodsUsers'] == 0) return '0.0';
            if (!$scoreInfo && $tempScoreInfo && $tempScoreInfo['goodsUsers'] != 0) {
                return bcdiv($tempScoreInfo['goodsScore'], $tempScoreInfo['goodsUsers'], 1);
            }
            if ($scoreInfo && !$tempScoreInfo && $scoreInfo['goodsUsers'] == 0) return '0.0';
            if ($scoreInfo && !$tempScoreInfo && $scoreInfo['goodsUsers'] != 0) {
                return bcdiv($scoreInfo['goodsScore'], $scoreInfo['goodsUsers'], 1);
            }
            if ($scoreInfo && $tempScoreInfo && $scoreInfo['goodsUsers'] + $tempScoreInfo['goodsUsers'] == 0) return '0.0';
            if ($scoreInfo && $tempScoreInfo && $scoreInfo['goodsUsers'] + $tempScoreInfo['goodsUsers'] != 0) {
                return bcdiv($scoreInfo['goodsScore'] + $tempScoreInfo['goodsScore'], $scoreInfo['goodsUsers'] + $tempScoreInfo['goodsUsers'], 1);
            }
            return '0.0';
        } while (false);
    }

    /**
     * 商品评论总数
     */
    public function goodsAppraiseNum($goodsId)
    {
        $num = DB::name('goods_appraises')
            ->where('goodsId', $goodsId)
            ->where('dataFlag', 1)
            ->where('isShow', 1)
            ->count();
        $tempNum = 0;
        if (config('web.temp_appraises_open')) {
            $tempNum = DB::name('goods_temp_appraises')
                ->where('goodsId', $goodsId)
                ->where('dataFlag', 1)
                ->where('isShow', 1)
                ->count();
        }
        return $num + $tempNum;
    }

    public function infoPageAppraiseList($goodsId)
    {
        if (!config('web.temp_appraises_open')) {
            $appraiseList = DB::name('goods_appraises')->alias('ga')
                ->leftJoin('users u', 'u.userId=ga.userId')
                ->where('ga.goodsId', $goodsId)
                ->where('ga.dataFlag', 1)
                ->where('ga.isShow', 1)
                ->field('u.userPhoto,u.nickname,u.userPhone,ga.goodsScore,ga.content,ga.images,ga.createTime')
                ->order('id', 'desc')
                ->limit(0, 2)
                ->select();
        } else {
            $appraiseList = DB::name('goods_appraises')->alias('ga')
                ->leftJoin('users u', 'u.userId=ga.userId')
                ->field('userPhoto,nickname,userPhone,goodsScore,content,images,ga.createTime')
                ->where('ga.goodsId', $goodsId)
                ->where('ga.dataFlag', 1)
                ->where('ga.isShow', 1)
                ->union(function ($query) use ($goodsId) {
                    $query->name('goods_temp_appraises')
                        ->where('goodsId', $goodsId)
                        ->where('dataFlag', 1)
                        ->where('isShow', 1)
                        ->field('userPhoto,nickname,nickname userPhone,goodsScore,content,images,createTime');
                }, true)
                ->order('createTime', 'desc')
                ->limit(0, 2)
                ->select();
        }
        foreach ($appraiseList as &$item) {
            $item['userPhoto'] = addImgDomain($item['userPhoto']);
            $item['nickname'] = $item['nickname']!=='' ? $item['nickname'] : hideUserPhone($item['userPhone']);
            unset($item['userPhone']);
            $item['images'] = explode(',', $item['images']);
            foreach ($item['images'] as $key=>$image) {
                if (empty($image)) {
                    unset($item['images'][$key]);
                    continue;
                }
                $item['images'][$key] = addImgDomain($image);
            }
        }
        unset($item);
        return $appraiseList;
    }

    /**
     * 获取商品规格配置
     */
    public function getSpecInfo($goodsId)
    {
        $cats = DB::name('spec_cats')->alias('cat')
            ->leftJoin('spec_items item', 'item.catId=cat.catId')
            ->where('item.dataFlag', 1)
            ->where('cat.dataFlag', 1)
            ->where('item.goodsId', $goodsId)
            ->field('distinct(cat.catId),cat.catName')
            ->select();
        $specItems = DB::name('spec_items')
            ->where('goodsId', $goodsId)
            ->where('dataFlag', 1)
            ->field('catId,itemId,itemName,itemImg')->select();
        $goodsSpec = DB::name('goods_specs')
            ->where('goodsId', $goodsId)
            ->where('dataFlag', 1)
            ->field('id,specIds,marketPrice,specPrice,tuanPrice,specStock,isDefault')
            ->select();
        foreach ($specItems as &$specItem) {
            $specItem['itemImg'] = $specItem['itemImg'] ? addImgDomain($specItem['itemImg']) : '';
        }
        unset($specItem);
        return [
            'cats' => $cats,
            'items' => $specItems,
            'spec' => $goodsSpec
        ];
    }

	/**
	 * 获取店铺商品列表
	 */
	public function shopGoods($shopId){
		$msort = input("param.msort/d");
		$mdesc = input("param.mdesc/d");
		$order = array('g.saleTime'=>'desc');
		$orderFile = array('1'=>'g.isHot','2'=>'g.saleNum','3'=>'g.shopPrice','4'=>'g.shopPrice','5'=>'(gs.totalScore/gs.totalUsers)','6'=>'g.saleTime');
		$orderSort = array('0'=>'asc','1'=>'desc');
		if($msort>0){
			$order = array($orderFile[$msort]=>$orderSort[$mdesc]);
		}
		$goodsName = input("param.goodsName");//搜索店鋪名
		$words = $where = $where2 = $where3 = $where4 = [];
		if($goodsName!=""){
			$words = explode(" ",$goodsName);
		}
		if(!empty($words)){
			$sarr = array();
			foreach ($words as $key => $word) {
				if($word!=""){
					$sarr[] = "g.goodsName like '%$word%'";
				}
			}
			$where4 = implode(" or ", $sarr);
		}

		$sprice = input("param.sprice");//开始价格
		$eprice = input("param.eprice");//结束价格
		if($sprice!="")$where2 = "g.shopPrice >= ".(float)$sprice;
		if($eprice!="")$where3 = "g.shopPrice <= ".(float)$eprice;
		$ct1 = input("param.ct1/d");
		$ct2 = input("param.ct2/d");
		if($ct1>0)$where['shopCatId1'] = $ct1;
		if($ct2>0)$where['shopCatId2'] = $ct2;
		$goods = Db::name('goods')->alias('g')
		->join('__GOODS_SCORES__ gs','gs.goodsId = g.goodsId','left')
		->where(['g.shopId'=>$shopId,'g.isSale'=>1,'g.goodsStatus'=>1,'g.dataFlag'=>1])
		->where($where)->where($where2)->where($where3)->where($where4)
		->field('g.goodsId,g.goodsName,g.goodsImg,g.shopPrice,g.marketPrice,g.saleNum,g.appraiseNum,g.goodsStock,g.isFreeShipping,gallery')
		->order($order)
		->paginate((input('pagesize/d')>0)?input('pagesize/d'):16)->toArray();
		return  $goods;
	}

    /*
     * 获取商品分类列表
     */
    public function getGoodsList($where, $offset=1, $pagesize=5, $order = 'weight', $sort= 'asc')
    {
        $goodsList = $this
            ->field("goodsId, goodsName, goodsImg, favoriteNum, shareNum, shopPrice, createTime")
            ->where('goodsStatus', '=', 1)
            ->where('isSale', '=', 1)
            ->where('dataFlag', '=', 1)
            ->where($where);
        $db = clone $goodsList;
        $db1 = clone $goodsList;
        $data['total']=$db->count();
        $data['list']=$db1->order($order, $sort)
            ->limit(($offset-1)*$pagesize, $pagesize)
            ->select()->toArray();
        return $data;
    }

    public function getGoodsByTagsId($tagsId, $isHide=false, $offset=1, $pageSize=5, $order = 'weight', $sort= 'asc')
    {
        if ($isHide==true) {
            $where[] = ['g.isAudit', '=', 0];
        }
        $where['g.goodsStatus'] = 1;
        $where['g.isSale'] = 1;
        $where['g.dataFlag'] = 1;
        if (!empty($tagsId)) $where[] = ['gl.tagId', 'in', explode(',',$tagsId)];
        $goodsList = $this->alias('g')
            ->leftJoin('goods_labels gl', 'g.goodsId=gl.goodsId')
            ->distinct('gl.goodsId')
            ->where($where)
            ->field("g.goodsId, g.goodsName, g.goodsImg, g.favoriteNum, g.shareNum, g.shopPrice, g.goodsCatIdPath, g.createTime")
            ->order($order, $sort)
            //->limit(($offset-1)*$pageSize, $pageSize)
            ->select()->toArray();
        return $goodsList;
    }

    public function getGoodsListAll($where, $order = 'weight', $sort= 'asc')
    {
        $goodsList = $this
            ->field("goodsId, goodsName, goodsImg, favoriteNum, shareNum, createTime")
            ->where('goodsStatus', '=', 1)
            ->where('isSale', '=', 1)
            ->where('dataFlag', '=', 1)
            ->where($where);
        $db = clone $goodsList;
        $db1 = clone $goodsList;
        $data['total']=$db->count();
        $data['list']=$db1->order($order, $sort)
            ->select()->toArray();
        return $data;
    }
    /*
     * 获取商品规格
     */
    public function getGoodsSpec($where, $field='*')
    {
        $goodsSpec = DB::name('goods_specs')->where($where)->field($field)->find();
        return $goodsSpec;
    }

    /*
     * 获取商品店铺详细信息
     */
    public function getGoodsShopInfo($goodsId)
    {
        $info = DB::name('goods')->alias('g')
            ->join('shops s', 's.shopId=g.shopId', 'left')
            ->where('g.goodsId', $goodsId)
            ->where('g.dataFlag', 1)
            ->field('s.shopId, s.isInvoice, s.freight, g.goodsName, g.goodsCatIdPath, g.goodsStock, g.shopPrice, g.goodsType, g.isSpec, g.isFreeShipping, g.isPintuan, g.goodsImg, g.goodsCatId')
            ->find();
        if (empty($info)) throw AE::factory(AE::GOODS_NOT_EXISTS);
        return $info;
    }

}
