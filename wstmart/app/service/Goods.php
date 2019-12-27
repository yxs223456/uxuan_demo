<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/6/26
 * Time: 15:24
 */
namespace wstmart\app\service;

use wstmart\common\model\Goods as MGoods;
use wstmart\common\helper\Redis;
use wstmart\common\model\GoodsActivityGoods;
use wstmart\common\model\Pintuans;
use wstmart\common\model\GoodsShare as CMGoodsShare;
use wstmart\common\service\Apis;
use wstmart\common\struct\CommonParams;
use wstmart\common\model\Users as U;
use wstmart\common\exception\AppException as AE;
use wstmart\common\service\TaskWelfare as CSTW;
use wstmart\common\model\GoodsShareBrowse as CMGSB;
use wstmart\common\service\Goods as CSGoods;

use think\Db;
class Goods
{
    public function recommendList($offset, $pageSize, $key, $userId, CommonParams $commonParams)
    {
        $isHide = (new Apis())->isHide($commonParams);
        try {
            $recommendData = $this->getRecommendListByRedis($offset, $pageSize, $key, $isHide);
        } catch (\Throwable $e) {
            $recommendData =  $this->getRecommendListByDb($offset, $pageSize, $isHide);
        }
        if ($userId && is_array($recommendData['list']) && count($recommendData['list'])) {
            $favoriteInfo = DB::name('goods_favorites')
                ->where('userId', $userId)
                ->where('goodsId', 'in', array_column($recommendData['list'], 'goodsId'))
                ->where('isDelete', 0)
                ->column('goodsId');
        } else {
            $favoriteInfo = [];
        }
        $CSGoodsServer = new CSGoods();
        $redis = Redis::getRedis();
        foreach ($recommendData['list'] as &$goods) {
            $goods['favoriteNum'] = $goods['favoriteNum'] + $CSGoodsServer->getAdditionalFavoriteCount($goods['goodsId'], $redis);
            $goods['shareNum'] = $goods['shareNum'] + $CSGoodsServer->getAdditionalShareCount($goods['goodsId'], $goods['createTime'], $redis);
            $goods['isFavorite'] = in_array($goods['goodsId'], $favoriteInfo)?true:false;
            $goods['goodsImg'] = addImgDomain($goods['goodsImg']);
        }
        unset($goods);
        return array_merge($recommendData, [
            'offset' => $offset,
            'pageSize' => $pageSize,
            'key' => $key
        ]);
    }

    protected function getRecommendListByRedis($offset, $pageSize, &$key, $isHide = false)
    {
        $redisPreKey = $isHide ? 'recommend_goods_shield_list_' : 'recommend_goods_list_';
        $redisKey = $redisPreKey . $key;
        $redisConfig = config('redis.');
        $redis = new Redis($redisConfig);
        $cacheCount = $redis->zCard($redisKey);
        if (!$cacheCount) {
            $key = getPre5MinuteDateKey();
            $redisKey = $redisPreKey . $key;
            $cacheCount = $redis->zCard($redisKey);
            if (!$cacheCount) {
                throw AE::factory(AE::COM_REQUEST_ERR);
            }
        }
        $start = ($offset - 1) * $pageSize;
        $end = ($offset * $pageSize) - 1;
        $cacheData = $redis->zRange($redisKey, $start, $end);
        if (is_array($cacheData) && count($cacheData)) {
            foreach ($cacheData as &$goods) {
                $goods = json_decode($goods, true);
                $goods['favoriteNum'] = (int)$redis->get('goods_favorite_num_' . $goods['goodsId']);
                $goods['shareNum'] = (int)$redis->get('goods_share_num_' . $goods['goodsId']);
            }
            unset($goods);
        } else {
            $cacheData = [];
        }
        return [
            'total' => $cacheCount,
            'list' => $cacheData,
        ];
    }

    protected function getRecommendListByDb($offset, $pageSize, $isHide = false)
    {
        $DB = DB::name('goods')
            ->where('isRecom', 1)
            ->where('isSale', 1)
            ->where('dataFlag', 1);
        if ($isHide) {
            $DB->where('isAudit', 0);
        }
        $goods = $DB->field('goodsId,goodsName,goodsImg,goodsCatIdPath,shopPrice,favoriteNum,shareNum,createTime')
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
        return [
            'total' => count($goods),
            'list' => array_slice($goods, ($offset - 1) * $pageSize, $pageSize),
        ];
    }

    /**
     *商品详情
     */
    public function info($goodsId)
    {
        $goodsModel = new MGoods();
        $goods = $goodsModel->getGoodsById($goodsId)->toArray();
        $goods['goodsImg'] = addImgDomain($goods['goodsImg']);
        $goods['goodsDesc'] = str_replace('/upload/', config('web.image_domain') . '/upload/', $goods['goodsDesc']);
        $goods['gallery'] = explode(',', $goods['gallery']);
        $imageInfo = DB::name('images')->whereIn('imgPath', $goods['gallery'])->column('imgInfo', 'imgPath');
        $goods['goodsImgs'] = explode(',', $goods['goodsImgs']);
        foreach ($goods['goodsImgs'] as $key=>$goodsImg) {
            if (empty($goodsImg)) {
                unset($goods['goodsImgs'][$key]);
            } else {
                $goods['goodsImgs'][$key] = addImgDomain($goodsImg);
            }
        }
        foreach ($goods['gallery'] as $key=>$value) {
            if (empty($value)) {
                unset($goods['gallery'][$key]);
                continue;
            }
            $image = addImgDomain($value);
            if (isset($imageInfo[$value]) && !empty($imageInfo[$value])) {
                $imgInfo = json_decode($imageInfo[$value], true);
            } else {
//                $imgInfo = getimagesize($image);
                $imgInfo = [
                    200,200, "mime" => ""
                ];
            }
            $goods['gallery'][$key] = [
                'image' => $image,
                'width' => $imgInfo[0],
                'height' => $imgInfo[1],
                'mime' => $imgInfo['mime'],
            ];
        }
        $goods['isFavorite'] = $goodsModel->checkIsFavorite($goodsId);
        if ($goods['isSpec']) {
            $goods['spec'] = $goodsModel->getSpecInfo($goodsId);
        }
        $pintuanModel = new Pintuans();
        $pintuanConfig = $pintuanModel->getPintuanByGoodsId($goodsId);
        if (empty($pintuanConfig)) {
            $goods['isPintuan'] = 0;
        } else {
            if ($pintuanConfig->tuanSum<=$pintuanConfig->tuanSaleNum) {
                $goods['isPintuan'] = 0;
            } else {
                $goods['isPintuan'] = 1;
                $goods['pintuan'] = $pintuanConfig->toArray();
            }
        }
        $goods['infoPageAppraises'] = $this->infoPageAppraises($goodsId);

        $activityGoodsModel = new GoodsActivityGoods();
        $goods['fanliActivity'] = $activityGoodsModel->checkGoodsInActivity($goodsId, config('enum.goodsActivityType.fanli.value'));

        $CSGoodsServer = new CSGoods();
        $redis = Redis::getRedis();

        $goods['favoriteNum'] = $goods['favoriteNum'] + $CSGoodsServer->getAdditionalFavoriteCount($goodsId, $redis);
        $goods['shareNum'] = $goods['shareNum'] + $CSGoodsServer->getAdditionalShareCount($goodsId, $goods['createTime'], $redis);

        //记录详情页uv
        $CSGoodsServer->goodsBrowseUv($goodsId, $redis);

        return $goodsOutput = $this->getGoodsInfoOutput($goods);
    }

    protected function getGoodsInfoOutput(array $goods)
    {
        $data = [
            'goodsId' => $goods['goodsId'],
            'goodsName' => $goods['goodsName'],
            'goodsImg' => $goods['goodsImg'],
            'goodsImgs' => $goods['goodsImgs'],
            'marketPrice' => $goods['marketPrice'],
            'shopPrice' => $goods['shopPrice'],
            'goodsStock' => $goods['goodsStock'],
            'goodsUnit' => $goods['goodsUnit'],
            'goodsDesc' => $goods['goodsDesc'],
            'gallery' => $goods['gallery'],
            'isSale' => $goods['isSale'],
            'isSpec' => $goods['isSpec'],
            'spec' => $goods['spec']??null,
            'isFavorite' => $goods['isFavorite']??false,
            'favoriteNum' => $goods['favoriteNum'],
            'shareNum' => $goods['shareNum'],
            'isPintuan' => $goods['isPintuan'],
            'infoPageAppraises' => $goods['infoPageAppraises'],
            'fanliActivity' => $goods['fanliActivity'],
        ];
        if ($goods['isPintuan'] == 1) {
            $data['pintuan'] = [
                'tuanId' => $goods['pintuan']['tuanId'],
                'tuanPrice' => $goods['pintuan']['tuanPrice'],
                'tuanNum' => $goods['pintuan']['tuanNum'],
                'goodsNum' => $goods['pintuan']['goodsNum'],
            ];
        }
        $data['shoppingRules'] = $this->shoppingRules();
        return $data;
    }

    protected function shoppingRules()
    {
        $data = [
            [
                'question' => '问：购买商品下单后多久发货？',
                'answer'=> [
                    '答：平台承诺48小时内发货，节假日顺延',
                ]
            ],
            [
                'question' => '问：平台购买商品有保障吗？',
                'answer' => [
                    '答：平台内所售商品均正品保障',
                ]
            ],
            [
                'question' => '问：收货后发现质量问题怎么办？',
                'answer' => [
                    '答：收货后7天内均可申请售后（食品类除外）',
                ]
            ],
        ];
        return $data;
    }

    public function infoPageAppraises($goodsId)
    {
        $goodsModel = new MGoods();
        $aveScore = $goodsModel->goodsAveScore($goodsId);
        $num = $goodsModel->goodsAppraiseNum($goodsId);
        $list = $goodsModel->infoPageAppraiseList($goodsId);
        return [
            'aveScore' => $aveScore,
            'total' => $num,
            'list' => $list
        ];
    }

    public function addFavorite($goodsId, $userId)
    {
        $goodsModel = new MGoods();
        $goodsModel->getGoodsById($goodsId);//检验产品是否存在
        $goodsModel->addFavorite($goodsId, $userId);
        (new CSTW())->favoriteGoods($userId, $goodsId);
        return true;
    }

    public function cancelFavorite($goodsId)
    {
        $goodsModel = new MGoods();
        $goodsModel->getGoodsById($goodsId);//检验产品是否存在
        $goodsModel->cancelFavorite($goodsId);
        return true;
    }

    public function share($goodsId, $userId)
    {
        $goodsModel = new MGoods();
        $goods = $goodsModel->getGoodsById($goodsId);
        $appName = config('web.app_name');
        if (!empty($userId)) {
            $user = (new U())->getUserById($userId);
            $sharer = $user['nickname'];
            $inviteCode = $user->inviteCode;
        } else {
            $inviteCode = '';
        }
        if (empty($sharer)) {
            $sharer = $appName;
        }
        return [
            'title' => "【好友力荐】{$sharer}分享给你{$goods->goodsName}",
            'desc' => '在' . $appName .'，发现你的优选生活！',
            'icon' => addImgDomain($goods->goodsImg),
            'link' => config('web.h5_url') . '/goods_detail?goodsId=' . $goodsId . '&inviteCode=' . $inviteCode . '&time=' . time(),
        ];
    }

    public function addShare($goodsId)
    {
        $goodsModel = new MGoods();
        $goodsModel->getGoodsById($goodsId);//检验产品是否存在
        $goodsModel->addShare($goodsId);
        return true;
    }

    public function browseIsReward($goodsId, $userId)
    {
        $isReward = (new CSTW())->browseIsReward($userId, $goodsId);
        $random = mt_rand(1, 1000);
        if (!$isReward) {
            $score = 0;
        } elseif ($random <= 700) {
            $score = mt_rand(10, 20);
        } elseif ($random <= 900) {
            $score = mt_rand(21, 30);
        } else {
            $score = mt_rand(31, 50);
        }
        return ['isReward'=>$isReward, 'score'=>$score, 'time'=>10];
    }

    public function browseReward($goodsId, $score, $userId)
    {
        $reward = (new CSTW())->browseGoods($userId, $score, $goodsId);
        return ['reward'=>$reward];
    }

    public function shareBrowseReward($inviteCode, $userId, $goodsId, $time)
    {
        $userMap = ['inviteCode' => $inviteCode];
        $friend = (new U())->getUser($userMap);
        if (empty($friend)) {
            return true;
        }
        $shareId = (new CMGoodsShare())->where(['userId'=>$friend['userId'],'goodsId'=>$goodsId])
            ->whereTime('createTime', '>=', date('Y-m-d H:i:s', $time))
            ->column('id');
        $shareBrowseInfo = [
            'sharer' => $friend['userId'],
            'goodsId' => $goodsId,
            'browser' => (int) $userId,
            'shareId' => $shareId,
        ];
        $browseId = (new CMGSB())->insertGetId($shareBrowseInfo);
        if ($userId) {
            $reward = (new CSTW())->shareBrowse($friend['userId'], $userId, $goodsId);
            if ($shareId && $reward) {
                (new CMGSB())->where('id', $browseId)->update(['isReward'=>1,'reward'=>$reward]);
            }
        }
        return true;
    }
}