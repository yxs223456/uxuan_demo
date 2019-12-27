<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/8/8
 * Time: 13:43
 */

namespace wstmart\common\service;

use wstmart\common\model\Goods as G;
use wstmart\common\model\GoodsCats as GC;
use wstmart\common\helper\Redis;
use wstmart\app\service\Users as ASUsers;
use wstmart\common\service\Apis;
use think\Db;
use wstmart\common\exception\AppException as AE;
use wstmart\app\model\GoodsCats as M;
use wstmart\common\model\SysConfigs;
use wstmart\common\model\AuditSwitchs;
use wstmart\common\struct\CommonParams;
use wstmart\common\model\Users;

class Goods
{
    public function getGoodsCatsData($catId, $offset, $pageSize, $keyTime, CommonParams $commonParams)
    {
        $isHide = (new Apis())->isHide($commonParams);
        try {
            $data = $this->getCatsListByRedis($catId, $offset, $pageSize, $keyTime, $isHide);
        } catch (\Throwable $e) {
            $data =  $this->getCatsListByDb($catId, $offset, $pageSize, $isHide);
        }
        $userId = ASUsers::getUserByCache()['userId'];
        $msg = $this->delReturnData($userId, $data);
        $msg['offset'] = $offset;
        $msg['pageSize'] = $pageSize;
        $msg['key'] = $keyTime;
        return $msg;
    }

    public function getGoodsTagsList($inviteCode, CommonParams $commonParams, $offset, $pageSize)
    {
        $m = new G();
        $isHide = (new Apis())->isHide($commonParams);
        if (empty($inviteCode)) {
            $where['userId'] = ASUsers::getUserByCache()['userId'];
        } else {
            $where['inviteCode'] = $inviteCode;
        }
        $userInfo = (new Users())->getUserInfo($where);
        if (empty($userInfo)) throw AE::factory(AE::COM_USERS_ERROR);
        $list = $m->getGoodsByTagsId($userInfo->favoriteGoodsTags, $isHide, $offset, $pageSize);

        if ($isHide == true) {
            $gc = new GC();
            $catArr = $gc->getAuditCats(1);
            foreach ($list as $k=>$item) {
                $goodsCatIdPath = str_replace(',','_',$item['goodsCatIdPath']);
                if (in_array($goodsCatIdPath, $catArr)) unset($list[$k]);
            }
        }
        $data['list'] = $list;
        $goodsTagsList = $this->delReturnData($userInfo->userId, $data);
        $msg['list'] = array_splice($goodsTagsList['list'], ($offset-1)*$pageSize, $pageSize);
        $msg['total'] = count($list);
        $msg['offset'] = $offset;
        $msg['pageSize'] = $pageSize;
        return $msg;
    }

    public function delReturnData($userId, $data)
    {
        if ($userId && !empty($data)) {
            $favoriteInfo = DB::name('goods_favorites')
                ->where('userId', $userId)
                ->where('goodsId', 'in', array_column($data['list'], 'goodsId'))
                ->where('isDelete', 0)
                ->column('goodsId');
        } else {
            $favoriteInfo = [];
        }

        $redis = Redis::getRedis();
        foreach ($data['list'] as $key => $value) {
            $data['list'][$key]['favoriteNum'] = $value['favoriteNum'] + $this->getAdditionalFavoriteCount($value['goodsId'], $redis);
            $data['list'][$key]['shareNum'] = $value['shareNum'] + $this->getAdditionalShareCount($value['goodsId'], $value['createTime'], $redis);
            $data['list'][$key]['isFavorite'] = in_array($value['goodsId'], $favoriteInfo) ? true : false;
            $data['list'][$key]['goodsImg'] = addImgDomain($value['goodsImg']);
        }
        return $data;
    }

    public function getCatsListByRedis($catId, $offset, $pageSize, &$keyTime, $isHide=false)
    {
        //先从缓存取，取不到在取数据库
        $redisConfig = config('redis.');
        $r = new Redis($redisConfig);
        $auditSwitch = (new SysConfigs())->getSysConfig(['configId'=>118]);
        if ($isHide==true) {
            $name = config('enum.CatsGoodsAuditSwitchList');
        } else {
            $name = config('enum.CatsGoodsList');
        }
        $key = $name.'_'.$catId.'_'.$keyTime;
        $cacheCount = $r->zCard($key);
        if (!$cacheCount) {
            $keyTime = getPre5MinuteDateKey();
            $key = $name.'_'.$catId.'_'.$keyTime;
            $cacheCount = $r->zCard($key);
            if (!$cacheCount) {
                throw AE::factory(AE::COM_REQUEST_ERR);
            }
        }
        $start = ($offset - 1) * $pageSize;
        $end = ($offset * $pageSize) - 1;
        $data = $r->zRange($key, $start, $end);
        if (is_array($data) && count($data)) {
            foreach ($data as &$goods) {
                $goods = json_decode($goods, true);
                $goods['favoriteNum'] = (int)$r->get('goods_favorite_num_' . $goods['goodsId']);
                $goods['shareNum'] = (int)$r->get('goods_share_num_' . $goods['goodsId']);
            }
            unset($goods);
        } else {
            $data = [];
        }
        return [
            'total' => $cacheCount,
            'list' => $data,
        ];
    }

    public function activityGoodsList($activity, $offset, $pageSize, $userId, CommonParams $commonParams)
    {
        $isHide = (new Apis())->isHide($commonParams);
        $activity = DB::name('goods_activity')->where('type2', $activity)->find();
        $goodsIds = DB::name('goods_activity_goods')->where('activityId', $activity['id'])->column('goodsId');
        $query = DB::name('goods')->whereIn('goodsId', $goodsIds)->where('isSale', 1)->where('dataFlag', 1);
        if ($isHide) {
            $query->where('isAudit', 0);
        }
        $goods = $query->field('goodsId,goodsName,goodsImg,goodsCatIdPath,shopPrice,favoriteNum,shareNum')
            ->order(['weight'=>'asc','goodsId'=>'desc'])
            ->select();
        $hideCatIds = DB::name('goods_cats')->where('isAudit', 1)->column('catId');
        foreach ($goods as $key=>&$good) {
            $goodsCatIdPath = explode('_', $good['goodsCatIdPath']);
            unset($good['goodsCatIdPath']);
            if ($isHide) {
                foreach ($goodsCatIdPath as $catId) {
                    if ($catId && in_array($catId, $hideCatIds)) {
                        unset($goods[$key]);
                        break;
                    }
                }
            }
        }
        $goodsCount = count($goods);
        $goodsList = array_slice($goods, ($offset - 1) * $pageSize, $pageSize);
        if ($userId && count($goodsList)) {
            $favoriteInfo = DB::name('goods_favorites')
                ->where('userId', $userId)
                ->whereIn('goodsId', array_column($goodsList, 'goodsId'))
                ->where('isDelete', 0)
                ->column('goodsId');
        } else {
            $favoriteInfo = [];
        }
        foreach ($goodsList as &$item) {
            $item['isFavorite'] = in_array($item['goodsId'], $favoriteInfo)?true:false;
            $item['goodsImg'] = addImgDomain($item['goodsImg']);
        }
        return [
            'total' => $goodsCount,
            'list' => $goodsList,
            'activity' => [
                'title' => $activity['title'],
                'bannerIsShow' => !!$activity['bannerIsShow'],
                'banner' => addImgDomain($activity['banner']),
                'link' => $activity['link'],
            ],
            'offset' => $offset,
            'pageSize' => $pageSize
        ];
    }

    public function getCatsListByDb($catId, $offset, $pageSize, $isHide=false)
    {
        $gc = new GC();
        $m = new G();
        $catArr = $gc->getParentIs($catId);
        $like = implode('_', $catArr);
        $like.='_%';
        $where[] =['goodsCatIdPath', 'like', $like];
        if ($isHide==true) {
            $where[] = ['isAudit', '=', 0];
        }
        return $m->getGoodsList($where, $offset, $pageSize);
    }

    public function getCatsPageQuery($userId, $catSortName, $catSortUserName)
    {
        $redisConfig = config('redis.');
        $redis = new Redis($redisConfig);
//        if (!empty($userId)) {
//            $isSet = model('app/GoodsCats')->getUserCatsortsInfoByUserId($userId);
//            if ($isSet) {
//                $rs = $redis->get($catSortUserName.'_'.$userId);
//                $rs = null;
//                if (empty($rs)) {
//                    $response = $this->getCatsPidToken();
//                    $catSort = model('app/GoodsCats')->sortCats($userId);
//                    $rs = $this->reGroupingGoodscat(json_decode($catSort, true), $response);
//                    if (empty($rs)) {
//                        $res = $this->getCatsPid();
//                        $redis->set($catSortUserName.'_'.$userId,$res);
//                        return $res;
//                    }
//                    $redis->set($catSortUserName.'_'.$userId,$rs);
//                }
//                return $rs;
//            }
//        }
        //$rs = $redis->get($catSortName);
//        $rs = null;
//        if (empty($rs)) {
            $rs = $this->getCatsPid();
            //$redis->set($catSortName, $rs);
        //}
        return $rs;
    }

    public function reGroupingGoodscat($rs, $response)
    {
        if (!is_array($rs)) throw AE::factory(AE::COM_ARRAY_ERR);
        $data = [];
        foreach ($rs as $k => $item) {
            if (array_key_exists($item,  $response)==false) {
                continue;
            }
            $data[] = $response[$item];
        }
        return $data;
    }

    /*
     * 修改排序
     */
    public function updateUserSort($userId)
    {
        $m = new M();
        $rs = $m->updateCatsSort($userId);
        pr($rs);
        $response = $this->getCatsPidToken();
        $sortArr = model('app/GoodsCats')->sortCats($userId);
        if (!$sortArr) return [];
        $sortArr = json_decode($sortArr, true);
        $data = [];
        foreach ($sortArr as $key=>$v) {
            if (array_key_exists($v,  $response)==false) {
                continue;
            }
            $data[] =  $response[$v];
        }
        $catSortUserName = config('enum.catSortUser');
        $redisConfig = config('redis.');
        $redis = new Redis($redisConfig);
        $redis->set($catSortUserName.'_'.$userId, $data);
        return $rs;
    }

    public function getCatsPid()
    {
        $userQuery = ASUsers::getUserByCache();
        $isHide = (new Apis())->isHide($userQuery['commonParams']);
        $rs = model('goodsCats')->listQuery(0,-1,$isHide);
        $response = [];
        foreach ($rs as $r) {
            $response[] = $r;
        }
        return $response;
    }

    public function getCatsPidToken()
    {
        $rs = model('goodsCats')->listQuery(0);
        $response = [];
        foreach ($rs as $r) {
            $response[$r['catId']] = $r;
        }
        return $response;
    }

    public function goodsBrowseUv($goodsId, $redis = null)
    {
        if ($redis == null) {
            $redis = Redis::getRedis();
        }

        $userId = ASUsers::getUserByCache()['userId'];
        if (empty($userId)) {
            return;
        }

        $redis->sadd('goodsBrowseUv_' . $goodsId, $userId);
    }

    public function getAdditionalFavoriteCount($goodsId, $redis = null)
    {
        if ($redis == null) {
            $redis = Redis::getRedis();
        }

        $count = $redis->scard('goodsBrowseUv_' . $goodsId);
        return $count;
    }

    public function getAdditionalShareCount($goodsId, $createTime, $redis = null)
    {
        if ($redis == null) {
            $redis = Redis::getRedis();
        }

        $deadline = strtotime($createTime) > strtotime('2018-12-11') ? strtotime($createTime) : strtotime('2018-12-11');
        $count = $redis->scard('goodsBrowseUv_' . $goodsId) + bcdiv(time() - $deadline, 86400);
        return $count;
    }
}