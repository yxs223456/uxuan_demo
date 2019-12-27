<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/14
 * Time: 11:38
 */
namespace wstmart\common\service;

use wstmart\common\service\Users as CSU;
use wstmart\common\model\Users as CMU;
use wstmart\common\model\GoodsShare as CMGoodsShare;
use wstmart\common\model\UserScores as CMUS;
use wstmart\common\model\UserAddress as CMUA;
use wstmart\app\model\UserScores as AMUS;
use wstmart\common\model\Goods as CMGoods;
use wstmart\common\model\Coupons as CMC;
use wstmart\common\model\CouponUsers as CMCU;
use wstmart\common\helper\Redis;
use wstmart\common\helper\WxOfficialAccounts;
use wstmart\common\model\GoodsShareBrowse;
use addons\coupon\model\Coupons as AddonsCMC;
use think\Db;

class TaskWelfare
{
    /**
     * 完善个人资料并添加收货地址后奖励100U币
     */
    public function completeInformation($userId)
    {
        $redis = Redis::getRedis();
        $redisKey = 'complete_information_' . $userId;
        if ($redis->get($redisKey, 0) == 1) {
            return;
        }
        $map = ['dataType' => 'completeInformation', 'userId' => $userId];
        if ((new CMUS())->getUserScore($map)) {
            $redis->set($redisKey, 1);
            return;
        }
        if (!$this->checkInformationComplete($userId)) {
            return;
        }
        $this->doCompleteInformationReward($userId);
        $redis->set($redisKey, 1);
    }

    protected function checkInformationComplete($userId)
    {
        if (!((new CMU())->checkInformationComplete($userId))) {
            return false;
        }
        $addressCount = (new CMUA)->getCount($userId);
        if ($addressCount == 0) {
            return false;
        }
        return true;
    }

    protected function doCompleteInformationReward($userId)
    {
        $scoreInfo = [
            'userId' => $userId,
            'score' => 100,
            'dataType' => 'completeInformation',
            'dataRemarks' => '完善资料获得100U币',
            'scoreType' => 1,
        ];
        $userScoreModel = new AMUS();
        $userScoreModel->add($scoreInfo, true);
    }

    /**
     * 添加喜欢的商品类型标签，奖励100U币
     */
    public function addFavoriteTag($userId)
    {
        $map = [
            'dataType' => 'addFavoriteTag',
            'userId' => $userId
        ];
        if ((new CMUS())->getUserScore($map)) {
            return;
        }
        $this->doAddFavoriteTagReward($userId);
    }

    protected function doAddFavoriteTagReward($userId)
    {
        $scoreInfo = [
            'userId' => $userId,
            'score' => 100,
            'dataType' => 'addFavoriteTag',
            'dataRemarks' => '添加喜欢的商品类型标签获得100U币',
            'scoreType' => 1,
        ];
        $userScoreModel = new AMUS();
        $userScoreModel->add($scoreInfo, true);
    }

    /**
     * 关注「每日优选APP」公众号（uxuan365）,可获得100U币奖励
     */
    public function bindUxuan($openId)
    {
        $accessToken = getUxuanAccessToken('');
        $wxUserInfo = (new WxOfficialAccounts())->getUserInfoByOpenId($openId, $accessToken);
        if (empty($wxUserInfo['unionid'])) {
            return;
        }
        $user = (new CMU())->getUser(['wxUnionId'=>$wxUserInfo['unionid']]);
        if (empty($user)) {
            return;
        }
        $map = [
            'dataType' => 'bindUxuan',
            'userId' => $user['userId'],
        ];
        if ((new CMUS())->getUserScore($map)) {
            return;
        }
        $this->doBindUxuanReward($user['userId']);
    }

    protected function doBindUxuanReward($userId)
    {
        $scoreInfo = [
            'userId' => $userId,
            'score' => 100,
            'dataType' => 'bindUxuan',
            'dataRemarks' => '关注「每日优选APP」公众号获得100U币',
            'scoreType' => 1,
        ];
        $userScoreModel = new AMUS();
        $userScoreModel->add($scoreInfo, true);
    }

    /**
     *每收一位徒弟奖励200U币
     */
    public function inviteFans($userId, $fansId)
    {
        $map = [
            'dataType' => 'inviteFans',
            'userId' => $userId,
            'dataId' => $fansId,
        ];
        if ((new CMUS())->getUserScore($map)) {
            return;
        }
        $this->doInviteFansReward($userId, $fansId);
    }

    protected function doInviteFansReward($userId, $fansId)
    {
        $score = 200;
        $scoreInfo = [
            'userId' => $userId,
            'score' => $score,
            'dataType' => 'inviteFans',
            'dataId' => $fansId,
            'dataRemarks' => '收徒成功获得200U币',
            'scoreType' => 1,
        ];
        $userScoreModel = new AMUS();
        $userScoreModel->add($scoreInfo, true);
        $fansScore = ceil($score/10);
        $this->fansReward($userId, $fansScore, 'fansInviteFans', '徒弟收徒获得' . $fansScore . 'U币');
    }

    /**
     * 拜师成功，奖励100U币
     */
    public function bindFans($fansId, $inviter)
    {
        $map = [
            'dataType' => 'bindFans',
            'userId' => $fansId,
        ];
        if ((new CMUS())->getUserScore($map)) {
            return;
        }
        $this->doBindFansReward($fansId, $inviter);
    }

    protected function doBindFansReward($fansId, $inviter)
    {
        $scoreInfo = [
            'userId' => $fansId,
            'score' => 100,
            'dataType' => 'bindFans',
            'dataId' => $inviter,
            'dataRemarks' => '拜师成功获得100U币',
            'scoreType' => 1,
        ];
        $userScoreModel = new AMUS();
        $userScoreModel->add($scoreInfo, true);
    }

    /**
     * 在商品详情页浏览10秒以上，并且到达底部，会奖励随机数量的U币，每天最多可完成3次
     */
    public function browseGoods($userId, $score, $goodsId)
    {
        if (!$this->isActivityGoods('browseGoods', $goodsId)) {
            return 0;
        }
        $map = [
            ['dataType', '=', 'browseGoods'],
            ['userId', '=', $userId],
            ['createTime', '>=', date('Y-m-d')],
        ];
        $todayBrowseGoodsRewardCount = (new CMUS())->getCount($map);
        if ($todayBrowseGoodsRewardCount >= 3) {
            return 0;
        }
        return $this->doBrowseGoodsReward($userId, $score, $goodsId);
    }

    protected function doBrowseGoodsReward($userId, $score, $goodsId)
    {
        $scoreInfo = [
            'userId' => $userId,
            'score' => $score,
            'dataType' => 'browseGoods',
            'dataId' => $goodsId,
            'dataRemarks' => '浏览商品获得' . $score . 'U币',
            'scoreType' => 1,
        ];
        $userScoreModel = new AMUS();
        $userScoreModel->add($scoreInfo, true);
        $fansScore = ceil($score/10);
        $this->fansReward($userId, $fansScore, 'fansBrowseGoods', '徒弟逛街获得' . $fansScore . 'U币');
        return $score;
    }

    /**
     * 将商品添加到喜欢，会奖励随机数量的U币，每天最多可完成3次，需要添加不同的商品
     */
    public function favoriteGoods($userId, $goodsId)
    {
        if (!$this->isActivityGoods('favoriteGoods', $goodsId)) {
            return;
        }
        $map = [
            ['dataType', '=', 'favoriteGoods'],
            ['userId', '=', $userId],
            ['dataId', '=', $goodsId],
            ['createTime', '>=', date('Y-m-d')],
        ];
        $rewardCount = (new CMUS())->getCount($map);
        if ($rewardCount) {
            return;
        }
        $map = [
            ['dataType', '=', 'favoriteGoods'],
            ['userId', '=', $userId],
            ['createTime', '>=', date('Y-m-d')],
        ];
        $todayBrowseGoodsRewardCount = (new CMUS())->getCount($map);
        if ($todayBrowseGoodsRewardCount >= 3) {
            return;
        }
        $this->doFavoriteGoodsReward($userId, $goodsId);
    }

    protected function doFavoriteGoodsReward($userId, $goodsId)
    {
        $random = mt_rand(1, 1000);
        if ($random <= 800) {
            $score = mt_rand(10, 20);
        } else {
            $score = mt_rand(21, 30);
        }
        $scoreInfo = [
            'userId' => $userId,
            'score' => $score,
            'dataType' => 'favoriteGoods',
            'dataId' => $goodsId,
            'dataRemarks' => '将商品添加到喜欢获得' . $score . 'U币',
            'scoreType' => 1,
        ];
        $userScoreModel = new AMUS();
        $userScoreModel->add($scoreInfo, true);
        $fansScore = ceil($score/10);
        $this->fansReward($userId, $fansScore, 'fansFavoriteGoods', '徒弟打CALL获得' . $fansScore . 'U币');
    }

    /**
     * 分享喜欢的商品给好友，好友浏览后会奖励随机数量的U币,分享给不同的好友才可以赚取U币
     */
    public function shareBrowse($userId, $browser, $goodsId)
    {
        if (!$this->isActivityGoods('shareBrowse', $goodsId)) {
            return 0;
        }
        $map = [
            ['dataType', '=', 'shareBrowse'],
            ['userId', '=', $userId],
            ['dataId', '=', $browser],
            ['createTime', '>=', date('Y-m-d')],
        ];
        if ((new CMUS())->getUserScore($map)) {
            return 0;
        }
        return $this->doShareBrowseReward($userId, $browser);
    }

    protected function doShareBrowseReward($userId, $browser)
    {
        $goodsShareBrowseModel = new GoodsShareBrowse();
        $uv = $goodsShareBrowseModel->getBrowseUv($userId);
        $pv = $goodsShareBrowseModel->getBrowsePv($userId);
        if ($uv == 0 || $pv == 0) {
            $temp = 1000;
        } else {
            $temp = bcdiv($uv, $pv, 3)*1000;
        }
        $random = mt_rand(1, 1000);
        if ($random <= $temp) {
            $score = mt_rand(16, 30);
        } else {
            $score = mt_rand(10, 15);
        }
        $scoreInfo = [
            'userId' => $userId,
            'score' => $score,
            'dataType' => 'shareBrowse',
            'dataId' => $browser,
            'dataRemarks' => '好友浏览您分享的商品获得' . $score . 'U币',
            'scoreType' => 1,
        ];
        $userScoreModel = new AMUS();
        $userScoreModel->add($scoreInfo, true);
        $fansScore = ceil($score/10);
        $this->fansReward($userId, $fansScore, 'fansShareBrowse', '徒弟种草获得' . $fansScore . 'U币');
        return $score;
    }

    /**
     *挑选喜欢的商品并发起拼团，会奖励随机数量的U币,每天最多可完成1次
     */
    public function pintaunOriginator($userId, $tuanOrderId, $goodsId)
    {
        if (!$this->isActivityGoods('pintaunOriginator', $goodsId)) {
            return;
        }
        $map = [
            ['dataType', '=', 'pintaunOriginator'],
            ['userId', '=', $userId],
            ['createTime', '>=', date('Y-m-d')],
        ];
        $todayBrowseGoodsRewardCount = (new CMUS())->getCount($map);
        if ($todayBrowseGoodsRewardCount >= 1) {
            return;
        }
        $this->doPintaunOriginatorReward($userId, $tuanOrderId);
    }

    protected function doPintaunOriginatorReward($userId, $tuanOrderId)
    {
        $score = mt_rand(60, 100);
        $scoreInfo = [
            'userId' => $userId,
            'score' => $score,
            'dataType' => 'pintaunOriginator',
            'dataId' => $tuanOrderId,
            'dataRemarks' => '发起拼团获得' . $score . 'U币',
            'scoreType' => 1,
        ];
        $userScoreModel = new AMUS();
        $userScoreModel->add($scoreInfo, true);
        $fansScore = ceil($score/10);
        $this->fansReward($userId, $fansScore, 'fansPintaunOriginator', '徒弟开团获得' . $fansScore . 'U币');
    }

    protected function fansReward($fansId, $fansScore, $dataType, $dataRemarks)
    {
        $userModel = (new CMU());
        $fans = $userModel->getUserById($fansId);
        if ($fans->inviter == 0) {
            return;
        }
        $scoreInfo = [
            'userId' => $fans->inviter,
            'score' => $fansScore,
            'dataType' => $dataType,
            'dataId' => $fansId,
            'dataRemarks' => $dataRemarks,
            'scoreType' => 1,
        ];
        $userScoreModel = new AMUS();
        $userScoreModel->add($scoreInfo, true);
    }

    protected function isActivityGoods($activity, $goodsId)
    {
        $query = DB::name('goods_activity')->alias('ga')
            ->leftJoin('goods_activity_goods gag', 'ga.id=gag.activityId')
            ->where('ga.type2', $activity)
            ->where('gag.goodsId', $goodsId)
            ->select();
        if (is_array($query) && count($query)) {
            return true;
        }
        return false;
    }

    public function taskIndex($userId)
    {
        $carousel = $this->getScoreAndCouponCarousel(10);
        $userModel = new CMU();
        $user = $userModel->getUserById($userId);
        $scoreInfo = $this->myScoreInfo($user);
        $taskInfo = $this->taskInfo($userId);
        $userService = new CSU();
        $signInfo = $userService->signInfo($userId);
        $couponModel = new AddonsCMC();
        $couponList = $couponModel->exchangeCouponslist($userId, 1, 5);
        return [
            'carousel' => $carousel,
            'scoreInfo' => $scoreInfo,
            'taskInfo' => $taskInfo,
            'signInfo' => $signInfo,
            'couponList' => $couponList['list'],
        ];
    }

    public function taskInfo($userId)
    {
        $taskInfo = [
            'completeInformation' => [
                'title' => '完善资料',
                'desc' => '完善个人资料并添加收货地址后奖励100枚',
                'reward' => '+100',
                'finish' => false,
                'infoFinish' => true,
            ],
            'addFavoriteTag' => [
                'title' => '选我喜欢',
                'desc' => '选择你喜欢的商品类型标签后奖励100U币，同时我们会推荐给你喜欢的商品',
                'reward' => '+100',
                'finish' => false,
            ],
            'bindUxuan' => [
                'title' => '绑定微信公众号',
                'desc' => '关注「每日优选APP」公众号(uxuan365)后，即可获得100U币奖励',
                'reward' => '+100',
                'finish' => false,
                'account' => '每日优选APP',
            ],
            'inviteFans' => [
                'title' => '收徒赚U币',
                'desc' => '每收一位徒弟奖励200U币，徒弟完成后获得U币，师傅也会额外获得奖励',
                'reward' => '+200/位',
                'finish' => false,
            ],
            'browseGoods' => [
                'title' => '爱逛街（0/3）',
                'desc' => '在商品详情页浏览10秒以上，并且到达底部，会奖励随机数量的U币，浏览的商品越多，赚取的金币越多',
                'reward' => '+10~50/次',
                'finish' => false,
            ],
            'favoriteGoods' => [
                'title' => '疯狂打CALL（0/3）',
                'desc' => '找到你喜欢的商品。在详情页中点亮小心心加入喜欢',
                'reward' => '+10~30/次',
                'finish' => false,
            ],
            'shareBrowse' => [
                'title' => '给好友种草',
                'desc' => '找到你喜欢的商品给好友，好友浏览后会奖励随机数量的U币，每天分享给不同的好友才可以赚取更多的金币哦～',
                'reward' => '+10~100/次',
                'finish' => false,
            ],
            'pintaunOriginator' => [
                'title' => '我的团长我的团（0/1）',
                'desc' => '挑选喜欢的商品并发起拼团，会奖励随机数量的U币',
                'reward' => '+10~100/次',
                'finish' => false,
            ],
        ];
        $userScoreModel = new CMUS();
        $todayDate = date('Y-m-d');
        $userTaskFinishInfo = $userScoreModel->where('userId', $userId)
            ->where("dataType in ('completeInformation','addFavoriteTag','bindUxuan') or 
            (dataType in ('inviteFans','browseGoods','favoriteGoods','shareBrowse','pintaunOriginator') and createTime >= '{$todayDate}')")
            ->field('dataType')
            ->column('dataType');
        $userTaskFinishInfo = array_count_values($userTaskFinishInfo);
        foreach ($userTaskFinishInfo as $type => $count) {
            if (in_array($type, ['completeInformation','addFavoriteTag','bindUxuan','pintaunOriginator'])) {
                $taskInfo[$type]['finish'] = true;
            }
            if (in_array($type, ['browseGoods','favoriteGoods'])) {
                $taskInfo[$type]['title'] = str_replace('0', $count, $taskInfo[$type]['title']);
                $taskInfo[$type]['finish'] = $count >= 3 ? true : false;
            }
            if (in_array($type, ['pintaunOriginator'])) {
                $taskInfo[$type]['title'] = str_replace('0', $count, $taskInfo[$type]['title']);
                $taskInfo[$type]['finish'] = $count >= 1 ? true : false;
            }
        }
        if ($taskInfo['completeInformation']['finish'] == false) {
            $taskInfo['completeInformation']['infoFinish'] = (new CMU)->checkInformationComplete($userId);
        }
        $userService = new CSU();
        $taskInfo['inviteFans']['fansNum'] = $userService->myFansNum($userId);
        $taskInfo['inviteFans']['fansScore'] = $userService->fansScore($userId);
        return $taskInfo;
    }

    public function myScoreInfo(\think\model $user)
    {
        $myScore = $user->userScore;
        $notIntoAccountScore = 0;
        $saveMoney = (new CMCU())->alias('cu')
            ->leftJoin('coupons c', 'c.couponId=cu.couponId')
            ->where('userId', $user->userId)
            ->where('isUse', 1)
            ->sum('c.couponValue');
        $scoreInfo = [
            'myScore' => $myScore,
            'notIntoAccountScore' => $notIntoAccountScore,
            'saveMoney' => $saveMoney,
        ];
        return $scoreInfo;
    }

    public function getScoreAndCouponCarousel($limit)
    {
        $redisConfig = config('redis.');
        $redis = new Redis($redisConfig);
        $enum = config('enum.');
        $scoreCarouselKey = $enum['score_carousel'];
        $couponCarouselKey = $enum['coupon_carousel'];
        $scoreCarousel = $redis->get($scoreCarouselKey, null);
        $couponCarouse = $redis->get($couponCarouselKey, null);
        if (!$scoreCarousel) {
            $scoreCarousel = json_encode((new CMUS())->scoreCarousel($limit), JSON_UNESCAPED_UNICODE);
            $redis->set($scoreCarouselKey, $scoreCarousel, 60);
        }
        if (!$couponCarouse) {
            $couponCarouse = json_encode((new CMC())->couponCarousel($limit), JSON_UNESCAPED_UNICODE);
            $redis->set($couponCarouselKey, $couponCarouse, 60);
        }
        $scoreCarousel = json_decode($scoreCarousel, true);
        foreach ($scoreCarousel as &$item) {
            $item = [
                'time' => $item['createTime'],
                'data' =>[
                    'userPhoto' => addImgDomain($item['userPhoto']),
                    'desc' => ($item['nickname'] ? $item['nickname'] : hideUserPhone($item['userPhone'])) .
                        transformTime(strtotime($item['createTime'])) . '获得了' . $item['score'] . 'U币',
                ],
            ];
        }
        $couponCarouse = json_decode($couponCarouse, true);
        foreach ($couponCarouse as &$item) {
            $item = [
                'time' => $item['createTime'],
                'data' => [
                    'userPhoto' => addImgDomain($item['userPhoto']),
                    'desc' => ($item['nickname'] ? $item['nickname'] : hideUserPhone($item['userPhone'])) .
                        transformTime(strtotime($item['createTime'])) . (in_array($item['type'], [3]) ? '兑换' : '领取') .
                        ($item['useCondition'] == 1 ? ('满' . $item['useMoney'] . '减' . $item['couponValue'] . '红包') :
                            ($item['couponValue'] . '元无门槛红包')),
                ],
            ];
        }
        $data = array_merge($scoreCarousel, $couponCarouse);
        $time = array_column($data, 'time');
        array_multisort($time, SORT_DESC, $data);
        $data = array_slice($data, 0, 10);
        return array_column($data, 'data');
    }

    public function browseGoodsIsFinish($userId)
    {
        $userScoreModel = new CMUS();
        $todayDate = date('Y-m-d');
        $browseGoodsCount = $userScoreModel->where('userId', $userId)
            ->where('dataType', 'browseGoods')
            ->where('createTime', '>=', $todayDate)
            ->count();
        $isFinish = $browseGoodsCount >= 3 ? true : false;
        return $isFinish;
    }

    public function fansRank($userId)
    {
        $userModel = new CMU();
        $userScoreModel = new CMUS();
        $fansRank = $userModel
            ->where('userId', '<>', 1)
            ->field('userId,userPhoto,nickname,userPhone,fansNum')
            ->order('fansNum', 'desc')
            ->limit(0, 100)
            ->select()->toArray();
        $userIds = array_column($fansRank, 'userId');
        $fansScore = $userScoreModel
            ->whereIn('userId', $userIds)
            ->whereIn('dataType', ['fansInviteFans','fansBrowseGoods','fansFavoriteGoods','fansShareBrowse','fansPintaunOriginator'])
            ->field('userId,sum(score) fansScore')
            ->group('userId')
            ->select()->toArray();
        $fansScore = array($fansScore, null, 'userId');
        foreach ($fansRank as &$item) {
            $item['nickname'] = empty($item['nickname']) ? hideUserPhone($item['userPhone']) : $item['nickname'];
            $item['userPhoto'] = addImgDomain($item['userPhoto']);
            $item['fansScore'] = isset($fansScore[$item['userId']]) ? $fansScore[$item['userId']] : 0;
            unset($item['userPhone']);
            unset($item['userId']);
        }
        unset($item);
        return [
            'myRank' => $this->myFansRank($userId),
            'list' => $fansRank,
        ];
    }

    protected function myFansRank($userId)
    {
        $userModel = new CMU();
        $userService = new CSU();
        $user = $userModel->getUserById($userId);
        $fansScore = $userService->fansScore($user->userId);
        $myFansRank = $userService->myFansBank($user->fansNum);
        return [
            'rank' => $myFansRank,
            'nickname' => empty($user->nickname) ? hideUserPhone($user->userPhone) : $user->nickname,
            'userPhoto' => addImgDomain($user->userPhoto),
            'fansNum' => $user->fansNum,
            'fansScore' => $fansScore,
        ];
    }

    public function bindFansCarousel()
    {
        $userScoreModel = new CMUS();
        $carousel = $userScoreModel->alias('us')
            ->leftJoin('users u', 'us.userId=u.userId')
            ->where('us.dataType', 'bindFans')
            ->field('u.nickname,us.score')
            ->order('us.scoreId', 'desc')
            ->limit(0, 10)
            ->select()->toArray();
        $rs = [];
        foreach ($carousel as $item) {
            $rs[] = $item['nickname'] . ' 拜师成功获得了' . $item['score'] . 'U币';
        }
        return $rs;
    }

    public function shareList($userId, $offset, $pageSize)
    {
        $shareList = (new CMGoodsShare())->where('userId', $userId)
            ->field('id,goodsId,createTime')->order('createTime', 'desc')
            ->select()->toArray();
        $shares = [];
        foreach ($shareList as $share) {
            $key = substr($share['createTime'], 0, 10) . '_' . $share['goodsId'];
            if (!isset($shares[$key])) {
                $shares[$key]['createTime'] = substr($share['createTime'], 0, 10);
                $shares[$key]['goodsId'] = $share['goodsId'];
            }
            $shares[$key]['shareIds'][] = $share['id'];
        }
        $count = count($shares);
        $shares = array_slice($shares, ($offset - 1) * $pageSize, $pageSize);
        $shares = array_values($shares);
        foreach ($shares as &$share) {
            $rewardList = (new GoodsShareBrowse())->alias('gsb')
                ->leftJoin('users u', 'gsb.browser=u.userId')
                ->whereIn('gsb.shareId', $share['shareIds'])
                ->where('gsb.browser', '<>', 0)
                ->field('u.nickname,u.userPhone,u.userPhoto,gsb.isReward,gsb.reward')
                ->select()->toArray();
            $share['fansBrowse'] = [];
            foreach ($rewardList as $item) {
                $share['fansBrowse'][] = [
                    'nickname' => empty($item['nickname']) ? hideUserPhone($item['userPhone']) : $item['nickname'],
                    'userPhoto' => addImgDomain($item['userPhoto']),
                    'isReward' => $item['isReward'],
                    'reward' => $item['reward'],
                ];
            }
            if (!isset($goods[$share['goodsId']])) {
                $goods[$share['goodsId']] = (new CMGoods())->getGoodsById($share['goodsId']);
            }
            $share['goods'] = [
                'goodsName' => $goods[$share['goodsId']]->goodsName,
                'goodsImg' => addImgDomain($goods[$share['goodsId']]->goodsImg),
                'shopPrice' => $goods[$share['goodsId']]->shopPrice,
            ];
            unset($share['shareIds']);
        }
        return [
            'total' => $count,
            'list' => $shares,
            'offset' => $offset,
            'pageSize' => $pageSize,
        ];
    }

    public function browseIsReward($userId, $goodsId)
    {
        if (!$this->isActivityGoods('browseGoods', $goodsId)) {
            return false;
        }
        $map = [
            ['dataType', '=', 'browseGoods'],
            ['userId', '=', $userId],
            ['createTime', '>=', date('Y-m-d')],
        ];
        $todayBrowseGoodsRewardCount = (new CMUS())->getCount($map);
        if ($todayBrowseGoodsRewardCount >= 3) {
            return false;
        }
        return true;
    }
}