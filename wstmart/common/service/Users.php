<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/6/22
 * Time: 9:54
 */
namespace wstmart\common\service;

use wstmart\common\model\VerificationCodes as VCodes;
use wstmart\common\service\Coupons as CSCoupons;
use wstmart\common\helper\Sms;
use wstmart\common\model\LogSms;
use wstmart\common\model\Users as CUser;
use wstmart\common\model\UserScores as CMUS;
use wstmart\common\model\CouponUsers as CMCouponUsers;
use wstmart\common\model\Coupons as CMCoupons;
use wstmart\common\model\SignReward as CMSignReward;
use wstmart\app\model\AppSession as session;
use wstmart\app\service\Users as ASUsers;
use think\Db;
use wstmart\app\model\Users as M;
use wstmart\common\exception\AppException as AE;
use wstmart\common\model\Users as U;
use wstmart\common\service\Users as SU;
use think\facade\Log;
use wstmart\common\service\TaskWelfare as CSTW;
use wstmart\common\helper\WeixinPay as WP;

class Users
{
    public function getVerificationCode($userPhone, $type)
    {
        $limitConfig = config('sms.verification_code_day_limit');
        $codeModel = new VCodes();
        $ip = getClientIp();
        if ($ip) {
            $ipTodayCodeCount = $codeModel->getTodayVerificationCodeCountByIp($ip);
            if ($ipTodayCodeCount >= $limitConfig['ip']) {
                throw AE::factory(AE::SMS_CODE_IP_EXCEED_LIMIT);
            }
        }
        if ($codeModel->getTodayVerificationCodeCountByPhone($userPhone) >= $limitConfig['phone']) {
            throw AE::factory(AE::SMS_CODE_PHONE_EXCEED_LIMIT);
        }
        if (time() <= $codeModel->getPreVerificationCodeSendTimeByPhone($userPhone) + 60) {
            throw AE::factory(AE::SMS_CODE_BUSINESS_LIMIT_CONTROL);
        }

        if (in_array($type, [
                'user_bind_mobile',
                'user_modify_bind_mobile',
                'user_wx_login',
                'user_mp_login',
                'user_h5_login',
                'bind_fans']) &&

            (new CUser)->checkPhoneIsBind($userPhone)) {
            throw AE::factory(AE::USER_PHONE_EXISTS_ALREADY);
        }

//        $verificationCode = getRandomString(6, true);
        $verificationCode = 666666;
        $codeModel->updateCodeStatus($userPhone, $type);//修改原验证码状态
        $codeModel->saveCode($userPhone, $type, $verificationCode, $ip);//记录验证码

        $smsConfig = config('sms.' . $type);
        $params = json_encode(['code'=>$verificationCode]);
//        $sendSms = Sms::sendSms($userPhone, $smsConfig['template'], $params, '每日优选');  //发送短信
//        $sendSms = json_encode($sendSms, JSON_UNESCAPED_UNICODE);
//        $logSmsModel = new LogSms();
//        $logSmsModel->saveLog($userPhone, 'getVerificationCode', $smsConfig['content'], $params, $sendSms, $ip); //记录短信
        return true;
    }

    public function phoneLogin($userPhone, $smsCode, $type)
    {
        $codeModel = new VCodes();
        if ($type == 'user_app_login') {
            $client = 'app';
        } elseif ($type == 'user_wx_login') {
            $client = 'wx';
        } elseif ($type == 'user_mp_login') {
            $client = 'mp';
        } elseif ($type == 'user_h5_login') {
            $client = 'h5';
        } else {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $codeModel->validateCode($userPhone, $smsCode, $type);      //判断验证码是否有效

        $userModel = new CUser();
        $user = $userModel->getUserByPhone($userPhone);
        if (empty($user)) {
            $userId = $userModel->createUserOnlyPhone($userPhone);
            $user = $userModel->getUserById($userId);
        }
        return $this->afterLogin($user, $client);
    }

    public function userInfo($userId)
    {
        $user = (new CUser())->getUserById($userId);
        $noviceTime = strtotime($user->createTime);
        $isNovice = bcsub(time(), $noviceTime) <= config('web.noviceCouponExpireDate') ? 1 : 0;
        $userInfo = [
            'nickname' => $user->nickname,
            'userPhoto' => addImgDomain($user->userPhoto),
            'userSex' => $user->userSex == 1 ? '男' : ($user->userSex == 2 ? '女' : ''),
            'brithday' => $user->brithday == null ? '' : $user->brithday,
            'userPhone' => $user->userPhone,
            'userScore' => $user->userScore,
            'inviteCode' => $user->inviteCode,
            'appBindWx' => (bool) $user->appOpenId,
            'isNovice' => $isNovice,
            'isReceive' => $user->isNovice,
        ];
        $signInfo = $this->signInfo($userId);
        return array_merge($userInfo, $signInfo);
    }

    public function unionidLogin($unionid)
    {
        $userModel = new CUser();
        $user = $userModel->getUserByUnionid($unionid);
        if (empty($user)) {
            throw AE::factory(AE::USER_NOT_EXISTS);
        }

        return $this->afterLogin($user, config('enum.clientType.mp.value'));
    }

    public function afterLogin(\think\model $user, $client)
    {
        $tokenId = $this->getTokenId($user->userId);
        $sessionModel = new session();
        $sessionModel->updateToken($user->userId, $tokenId, $client);
        $signInfo = $this->signInfo($user->userId);
        $noviceTime = strtotime($user->createTime);
        $isNovice = bcsub(time(), $noviceTime) <= config('web.noviceCouponExpireDate') ? 1 : 0;

        $pintuanPriceBuy = false;
        if (!empty($user->wxUnionId)) {
            $fanliUser = DB::name('fanli_shopkeepers')->where('unionId', $user->wxUnionId)->find();
            if (!empty($fanliUser)) {
                $pintuanPriceBuy = true;
            }
        }

        return [
            'token' => $tokenId,
            'user' => [
                'nickname' => $user->nickname,
                'userPhoto' => addImgDomain($user->userPhoto),
                'userSex' => $user->userSex == 1 ? '男' : ($user->userSex == 2 ? '女' : ''),
                'brithday' => $user->brithday == null ? '' : $user->brithday,
                'userPhone' => $user->userPhone,
                'userScore' => $user->userScore,
                'inviteCode' => $user->inviteCode,
                'todayIsSign' => $signInfo['todayIsSign'],
                'continuousSignDays' => $signInfo['continuousSignDays'],
                'appBindWx' => (bool) $user->appOpenId,
                'isNovice' => $isNovice,
                'isReceive' => $user->isNovice,
                'pintuanPriceBuy' => $pintuanPriceBuy,
                'mpOpenId' => !!$user->mpOpenId,
            ],
        ];
    }

    /*
     * 通过微信登录绑定手机号
     */
    public function wechatBindUserPhone($userPhone, $smsCode, $wechatInfo, $pid)
    {
        $codeModel = new VCodes();
        $codeModel->validateCode($userPhone, $smsCode, 'user_'.$wechatInfo['wechatType'].'_login');      //判断验证码是否有效
        if ($wechatInfo['wechatType']=='app') {
            $openidType = 'appOpenId';
        } elseif($wechatInfo['wechatType']=='wx' || $wechatInfo['wechatType']=='h5') {
            $openidType = 'wxOpenId';
        } elseif($wechatInfo['wechatType']=='mp') {
            $openidType = 'mpOpenId';
        } else {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $wechatData = [
            $openidType => $wechatInfo['openid'],
            'nickname'=>$wechatInfo['nickname'],
            'userPhoto'=>$wechatInfo['userPhoto'],
            'wxUnionId'=>$wechatInfo['unionid'],
        ];
        $userModel = new CUser();
        $userInfo = $userModel->getUserInfo(['wxUnionId'=>$wechatInfo['unionid']]);
        if ($userInfo) {
            throw AE::factory(AE::USER_AUTH_BIND);
        }
        $userInfo = $userModel->getUserInfo(['userPhone'=>$userPhone]);
        if ($userInfo && empty($userInfo->$openidType)) {
            if (empty($userInfo->pid)) {
                $wechatData['pid'] = $pid;
                $wechatData['bindPidTime'] = date('Y-m-d H:i:s');
            }
            $userModel->updateUserInfo(['userId'=>$userInfo->userId], $wechatData);
            $user = $userModel->getUserById($userInfo->userId);
//            (new CSCoupons())->sendLotteryCoupons($wechatInfo['unionid'], $userInfo->userId);
            (new CSCoupons())->sendFanliCoupons($wechatInfo['unionid'], $userInfo->userId);
            return $this->afterLogin($user, $wechatInfo['wechatType']);
        } elseif (!$userInfo) {
            $userphoneArr = '15726653645，18810134744，17600742700，13718550421，15210487012，13644033921，18801257126，18610665312，15708479069，17621669400，18842660366，18970145987，18161921801，17791419984，13149287582，15991215342，18602931259，13804203348，18065393115，15081376830，18618351562，18862602925，15651885955，15119100315，18513340615，15810671152，15701673525，13671247624，18810408422，15011500090，13811038229，18518133416，13799983009，18511967446，15260226250，13661117965，13810620906，13811332429，13661396280，18601399729，13910634929，13466327105，13910863227，13810001005，15901201841，18600806350，13910693347，18614055509，15810639495，18611056036，13001198972，13910922102，13910687521，13911908664，13810549909，15120041977，18618498660，13311137761，18602106689，18611346057，18810331668，13901250242，17759219931，17717680303，18911352327，13560004026，18611921181，13811187587，13621198703，13466395390，13911355436，13910507183，13501376274，13811098126，18311211922，18600184180，18610448098，18612032209，18817711181，15200843197，13910102403，13925249965，13910403290，15087054814，18501318014，13641332604，18910847255，18911303637，18610669211，18600423031，13711442941，13641168278';
            $phone = explode('，',$userphoneArr);
            $couponId = 14;
            $wechatData['pid'] = $pid;
            $wechatData['bindPidTime'] = $pid ? date('Y-m-d H:i:s') : '0000-00-00 00:00:00';
            $userId = $userModel->createUserOnlyPhoneAndWechat($userPhone, $wechatData);
            if (!$userId) throw AE::factory(AE::COM_MOBILE_BIND_FAIL);
            if (in_array($userPhone, $phone)) {
                $isCoupon = Db::name('coupon_users')->where(['userId'=>$userId, 'couponId'=>$couponId])->find();
                if (!$isCoupon) {
                    $data['shopId'] = 1;
                    $data['couponId'] = $couponId;
                    $data['isUse'] = 0;
                    $data['createTime'] = date('Y-m-d H:i:s');
                    $data['endTime'] = '2018-10-21';
                    $data['userId'] = $userId;
                    Db::name('coupon_users')->insert($data);
                }
            }
            $user = $userModel->getUserById($userId);
//            (new CSCoupons())->sendLotteryCoupons($wechatInfo['unionid'], $userId);
            (new CSCoupons())->sendFanliCoupons($wechatInfo['unionid'], $userId);
            return $this->afterLogin($user, $wechatInfo['wechatType']);
        } else {
            throw AE::factory(AE::USER_AUTH_BIND);
        }
    }

    /*
     * 解绑微信
     */
    public function untieWechat($userId)
    {
        $userId = ASUsers::getUserByCache()['userId'];
        $userModel = new CUser();
        $user = $userModel->getUserById($userId);
        if ((time()-strtotime($user->wechatBindTime)) < config('web.wx_untie_time')) {
            throw AE::factory(AE::WECHAT_PAY_ORDERS_NOT_UNTIE_BIND);
        }
        $data = [
            'wxUnionId'=>'',
            'appOpenId'=>'',
            'mpOpenId'=>'',
            'wxOpenId'=>'',
        ];
        $m = new M();
        $map['userId'] = $userId;
        $res = $m->userUpdate($map, $data);
        if ($res==0) throw AE::factory(AE::WECHAT_UNTIE_FAIL);
        return true;
    }

    /*
     * app绑定微信
     */
    public function appBindWechat($userId, $code)
    {
        $userModel = new CUser();
        $u = new M();
        $user = $userModel->getUserById($userId);
        if (!empty($user->appOpenId)) throw AE::factory(AE::WECHAT_AUTH_BIND);
        $appId = config('weixin.app.appid');
        $secret = config('weixin.app.appsecret');
        $access_token = $this->getAccessToken($appId, $secret, $code);
        $wechatInfo = $this->getUserInfo($access_token['access_token'],$access_token['openid']);
        $userInfo = $u->getUserInfo(['appOpenId'=>$access_token['openid']]);
        if ($userInfo) throw AE::factory(AE::WECHAT_NUMBER_IS_BIND);
        $wechatData['appOpenId'] = $access_token['openid'];
        if (empty($user->wxUnionId)) $wechatData['wxUnionId'] = $wechatInfo['unionid'];
        $wechatData['wechatBindTime'] = date('Y-m-d H:i:s');
        $selectPhoto = $u->getUserInfo(['userId'=>$userId]);
        $userPhoto = $selectPhoto->userPhoto;
        $nickName = $selectPhoto->nickname;
        if (empty($selectPhoto->userPhoto)) {
            $wechatData['userPhoto'] = $wechatInfo['headimgurl'];
            $userPhoto = $wechatInfo['headimgurl'];
        }
        if (empty($selectPhoto->nickname)) {
            $wechatData['nickName'] = $wechatInfo['nickName'];
            $nickName = $wechatInfo['nickName'];
        }
//        (new CSCoupons())->sendLotteryCoupons($wechatInfo['unionid'], $userId);
        (new CSCoupons())->sendFanliCoupons($wechatInfo['unionid'], $userId);
        $isBind = $u->userUpdate(['userId'=>$userId], $wechatData);
        if(!$isBind) throw AE::factory(AE::WECHAT_PARAM_SAVE_FAIL);
        return ['nickName'=>$nickName,'userPhoto'=>$userPhoto];
    }

    public function favoriteGoodsTags($userId, $favoriteGoodsTags)
    {
        $user = (new U())->getUserById($userId);
        $user->favoriteGoodsTags = $favoriteGoodsTags;
        $user->save();
        $taskWelfareService = new CSTW();
        $taskWelfareService->addFavoriteTag($userId);
        return true;
    }

    public function shareBindFans($userId)
    {
        $user = (new U())->getUserById($userId);
        $appName = config('web.app_name');
        $sharer = $user['nickname'];
        if (empty($sharer)) {
            $sharer = hideUserPhone($user['userPhone']);
        }
        return [
            'title' => "好友{$sharer}邀请你加入{$appName}",
            'desc' => '好货低价购，海量任你选，加入每日优选立即奖励100U币！',
            'icon' => '',
            'link' => config('web.static_url') . '/pages/chooseTeacher.html?inviteCode=' . $user['inviteCode'],
        ];
    }

    public function shareMyFavoriteGoods($userId)
    {
        $user = (new U())->getUserById($userId);
        $nickname = empty($user['nickname']) ? hideUserPhone($user['userPhone']) : $user['nickname'];

        return [
            'title' => $nickname . "推荐你购买",
            'desc' => '人工智能帮我挑选的商品，胜似我帮你挑选的商品，别犹豫，来戳我',
            'icon' => addImgDomain($user['userPhoto']),
            'link' => config('web.static_url') . '/pages/recommend.html?inviteCode=' . $user['inviteCode'],
        ];
    }

    public function bindFans($userPhone, $smsCode, $inviteCode)
    {
        $userModel = new CUser();
        $inviter = $userModel->getUser(['inviteCode'=>$inviteCode]);
        if (empty($inviter)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }

        $codeModel = new VCodes();
        $codeModel->validateCode($userPhone, $smsCode, 'bind_fans');      //判断验证码是否有效

        $fans = $userModel->getUserByPhone($userPhone);
        if (!empty($fans)) {
            throw AE::factory(AE::USER_PHONE_EXISTS_ALREADY);
        }
        $fansId = $userModel->createUserOnlyPhone($userPhone);
        $fans = $userModel->getUserById($fansId);
        $fans->inviter = $inviter->userId;
        $taskWelfareService = new CSTW();
        Db::startTrans();
        try {
            $fans->save();
            $inviter->fansNum += 1;
            $inviter->save();
            $taskWelfareService->inviteFans($inviter->userId, $fansId);
            $taskWelfareService->bindFans($fansId, $inviter->userId);
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function fansInfo($userId)
    {
        $myFansNum = $this->myFansNum($userId);
        $fansScore = $this->fansScore($userId);
        $inviter = $this->myInviter($userId);
        $fansInfo = [
            'myFansNum' => $myFansNum,
            'fansScore' => $fansScore,
            'inviter' => empty($inviter) ? null : [
                'userPhoto' => $inviter->userPhoto,
                'nickname' => empty($inviter->nickname) ? hideUserPhone($inviter->userPhone) : $inviter->nickname,
            ],
        ];
        return $fansInfo;
    }

    public function fansList($userId, $offset, $pageSize)
    {
        $fansList = DB::name('users')->where('inviter', $userId)
            ->field('userId,inviteCode,nickname,userPhone,userPhoto,userScore,lastTime,createTime')
            ->select();
        $total = count($fansList);
        $fansIds = array_column($fansList, 'userId');
        $fansFansCount = DB::name('users')
            ->where('inviter', 'in', $fansIds)
            ->field('count(userId) fansCount,inviter')->group('inviter')->select();
        $fansFansCount = array_column($fansFansCount, 'fansCount', 'inviter');
        $fansScore = DB::name('user_scores')->where('userId', $userId)
            ->where('dataId', 'in', $fansIds)
            ->where('dataType', 'in', ['fansInviteFans', 'fansBrowseGoods', 'fansFavoriteGoods', 'fansShareBrowse', 'fansPintaunOriginator'])
            ->field('sum(score) fansScore,dataId')->group('dataId')->select();
        $fansScore = array_column($fansScore, 'fansScore', 'dataId');
        foreach ($fansList as &$item) {
            $item['userPhoto'] = addImgDomain($item['userPhoto']);
            $item['nickname'] = empty($item['nickname']) ? hideUserPhone($item['userPhone']) : $item['nickname'];
            $item['inviteTime'] = substr($item['createTime'], 0, 10);
            $item['fansScore'] = empty($fansScore[$item['userId']]) ? 0 : $fansScore[$item['userId']];
            $item['fansCount'] = empty($fansFansCount[$item['userId']]) ? 0 : $fansFansCount[$item['userId']];
            $item['lastTime'] = strtotime($item['lastTime']);
            unset($item['userPhone']);
            unset($item['createTime']);
            unset($item['userId']);
        }
        unset($item);
        $fansScoreOrder = array_column($fansList, 'fansScore');
        array_multisort($fansScoreOrder, SORT_DESC, $fansList);
        $fansList = array_slice($fansList, ($offset - 1) * $pageSize, $pageSize);
        $rs = [
            'total' => $total,
            'list' => $fansList,
            'time' => time(),
            'offset' => $offset,
            'pageSize' => $pageSize,
        ];
        return $rs;
    }

    public function recallFans($userId, $inviteCode)
    {
        return true;
    }

    public function myFansNum($userId)
    {
        $userModel = new CUser();
        $myFansNum = $userModel->getUserById($userId)->fansNum;
        return $myFansNum;
    }

    public function myFansBank($myFansNum)
    {
        $userModel = new CUser();
        $myFansBank = $userModel->where('fansNum', '>', $myFansNum)->count();
        return $myFansBank + 1;
    }

    public function fansScore($userId)
    {
        $userScoreModel = new CMUS();
        $fansScore = $userScoreModel
            ->where('userId', $userId)
            ->where('dataType', 'in', ['fansInviteFans', 'fansBrowseGoods', 'fansFavoriteGoods', 'fansShareBrowse', 'fansPintaunOriginator'])
            ->sum('score');
        return $fansScore;
    }

    public function myInviter($userId)
    {
        $userModel = new CUser();
        $user = $userModel->getUserById($userId);
        if (empty($user->inviter)) {
            return null;
        }
        $inviter = $userModel->getUserById($user->inviter);
        return $inviter;
    }

    public function setNewsNoDisturb($isDisturb, $receiveTime)
    {
        $u = new M();
        $userId = ASUsers::getUserByCache()['userId'];
        $data['isReceiveNews'] = $isDisturb;
        $data['receiveTime'] = $receiveTime ?? '';
        $u->userUpdate(['userId'=>$userId], $data);
        return true;
    }

    public function newsNoDisturbInfo()
    {
        $u = new M();
        $userId = ASUsers::getUserByCache()['userId'];
        $rs = $u->getUserInfo(['userId'=>$userId], 'isReceiveNews as isDisturb,receiveTime');
        return $rs;
    }

    public function setSignRemind($status)
    {
        $u = new M();
        $userId = ASUsers::getUserByCache()['userId'];
        $data['signRemind'] = $status;
        $u->userUpdate(['userId'=>$userId], $data);
        return true;
    }

    public function modifyPhone($userPhone, $smsCode)
    {
        $codeModel = new VCodes();
        $codeType = 'user_modify_bind_mobile';

        $userModel = new CUser();
        if ($userModel->checkPhoneIsBind($userPhone)) {//判断手机号是否占用
            throw AE::factory(AE::USER_PHONE_EXISTS_ALREADY);
        }
        $codeModel->validateCode($userPhone, $smsCode, $codeType);      //判断验证码是否有效

        $userId = ASUsers::getUserByCache()['userId'];
        $user = $userModel->getUserById($userId);
        $user->userPhone = $userPhone;
        $user->save();
        return true;
    }

    /**
     * 计算tokenId
     */
    private function getTokenId($userId) {
        return $this->to_guid_string(sprintf('%011d',$userId).time());
    }

    /**
     * 根据PHP各种类型变量生成唯一标识号
     * @param mixed $mix 变量
     * @return string
     */
    private function to_guid_string($mix) {
        if (is_object($mix)) {
            return spl_object_hash($mix);
        } elseif (is_resource($mix)) {
            $mix = get_resource_type($mix) . strval($mix);
        } else {
            $mix = serialize($mix);
        }
        return md5($mix);
    }

    public function editUser($type, $value)
    {
        $userId = ASUsers::getUserByCache()['userId'];
        DB::name('users')
            ->where('userId', $userId)
            ->data(["$type"=>$value])
            ->update();
        (new CSTW())->completeInformation($userId);
        return true;
    }

    public function edit($userId, $userPhoto, $nickname, $userSex, $brithday)
    {
        $info = ['userPhoto'=>$userPhoto,'nickname'=>$nickname,'userSex'=>$userSex,'brithday'=>$brithday];
        foreach ($info as $key => $value) {
            if ($value === null) {
                unset($info[$key]);
            }
        }
        if (count($info)) {
            DB::name('users')
                ->where('userId', $userId)
                ->data($info)
                ->update();
        }
        (new CSTW())->completeInformation($userId);
        return true;
    }

    public function sign($userId)
    {
        $userModel = new CUser();
        $user = $userModel->getUserById($userId);
        $todayDate = date('Y-m-d');
        if ($user->lastSignDate == $todayDate) {
            return $this->signInfo($userId);
        }

        Db::startTrans();
        try {
            $userModel->todaySign($userId);
            $yesterdayDate = date('Y-m-d', time() - 86400);
            if ($user->lastSignDate == $yesterdayDate) {
                $user->continuousSignDays += 1;
            } else {
                $user->continuousSignDays = 1;
            }
            $user->lastSignDate = $todayDate;
            $user->save();
            $this->signReward($user);
            Db::commit();
            return $this->signInfo($userId);
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    protected function signReward(\think\model $user)
    {
        $continuousAwardDays = bcmod($user->continuousSignDays - 1, 7);
        $rewardType = 'score';
        $reward = $score = config('web.day_login_award');
        if ($continuousAwardDays == 2) {
            $rewardType = 'coupon';
        } elseif ($continuousAwardDays == 6) {
            return;
        }
        $couponId = 0;
        if ($rewardType == 'score') {
            $scoreInfo = [
                'userId' => $user->userId,
                'score' => $score,
                'dataType' => 'sign',
                'dataRemarks' => '连续' . ($continuousAwardDays + 1) .'天签到，获得' . $score .'U币',
                'scoreType' => 1,
            ];
            model('app/userScores')->add($scoreInfo, true);
        } elseif ($rewardType == 'coupon') {
            $couponModel = new CMCoupons();
            $coupon = $couponModel->getCouponsInfo(['type'=>2,'couponValue'=>3], 'couponId');
            $couponInfo = [
                'shopId' => 1,
                'couponId' => $coupon->couponId,
                'userId' => $user->userId,
                'isUse' => 0,
                'createTime' => date('Y-m-d H:i:s'),
                'endTime' => date('Y-m-d', strtotime('+30day')),
            ];
            (new CMCouponUsers())->insert($couponInfo);
            $reward = 3;
            $couponId = $coupon->couponId;
        }
        $signRewardInfo = [
            'userId' => $user->userId,
            'date' => date('Y-m-d'),
            'rewardType' => $rewardType,
            'reward' => $reward,
            'couponId' => $couponId,
        ];
        (new CMSignReward())->insert($signRewardInfo);
    }

    public function signLottery($userId)
    {
        $user = (new U())->getUserById($userId);
        if ($user->lastSignDate !== date('Y-m-d')) {
            throw AE::factory(AE::USER_TODAY_NOT_SIGN);
        }
        if (bcmod($user->continuousSignDays, 7) != 0) {
            throw AE::factory(AE::USER_CONTINUES_SIGN_ERR);
        }
        $reward = (new CMSignReward())->where(['userId'=>$userId,'date'=>date('Y-m-d')])->find();
        if ($reward) {
            throw AE::factory(AE::USER_SING_LOTTERY_ALREADY);
        }
        return $this->doSignLottery($userId);
    }

    protected function doSignLottery($userId)
    {
        $couponModel = new CMCoupons();
        $coupon1 = $couponModel->getCouponsInfo(['type'=>2,'couponValue'=>3], 'couponId');
        $coupon2 = $couponModel->getCouponsInfo(['type'=>2,'couponValue'=>5], 'couponId');
        $rewards = [
            ['type'=>'coupon', 'value'=>3, 'reward'=>false],
            ['type'=>'coupon', 'value'=>5, 'reward'=>false],
            ['type'=>'score', 'value'=>288, 'reward'=>false],
        ];
        $rand = mt_rand(0, 2);
        $reward = $rewards[$rand];
        $rewards[$rand]['reward'] = true;
        if ($reward['type'] == 'coupon') {
            $couponId = $reward['value'] == 3 ? $coupon1->couponId : $coupon2->couponId;
            $couponInfo = [
                'shopId' => 1,
                'couponId' => $couponId,
                'userId' => $userId,
                'isUse' => 0,
                'createTime' => date('Y-m-d H:i:s'),
                'endTime' => date('Y-m-d', strtotime('+30day')),
            ];
            (new CMCouponUsers())->insert($couponInfo);
        } else {
            $scoreInfo = [
                'userId' => $userId,
                'score' => $reward['value'],
                'dataType' => 'signLottery',
                'dataRemarks' => '连续7天签到，抽奖获得' . $reward['value'] .'U币',
                'scoreType' => 1,
            ];
            model('app/userScores')->add($scoreInfo, true);
            $couponId = 0;
        }
        $signRewardInfo = [
            'userId' => $userId,
            'date' => date('Y-m-d'),
            'rewardType' => $reward['type'],
            'reward' => $reward['value'],
            'couponId' => $couponId,
            'isLottery' => 1,
        ];
        (new CMSignReward())->insert($signRewardInfo);
        return $rewards;
    }

    public function signInfo($userId)
    {
        $userModel = new CUser();
        $user = $userModel->getUserById($userId);
        $todayDate = date('Y-m-d');
        $todayIsSign = ($user['lastSignDate'] == $todayDate);
        $continuousSignDays = $user['continuousSignDays'];
        if (!$todayIsSign) {
            $yesterdayDate = date('Y-m-d', time() - 86400);
            if ($user['lastSignDate'] != $yesterdayDate) {
                $continuousSignDays = 0;
            }
        }
        $rewardInfo = [
            ['rewardType'=>'score','reward'=>50,'isSign'=>false],
            ['rewardType'=>'score','reward'=>50,'isSign'=>false],
            ['rewardType'=>'coupon','reward'=>'3元','isSign'=>false],
            ['rewardType'=>'score','reward'=>50,'isSign'=>false],
            ['rewardType'=>'score','reward'=>50,'isSign'=>false],
            ['rewardType'=>'score','reward'=>50,'isSign'=>false],
            ['rewardType'=>'lottery','reward'=>'unknown','isSign'=>false],
        ];
        if ($todayIsSign) {
            $continuousAwardDays = bcmod($continuousSignDays-1, 7);
            foreach ($rewardInfo as $key => &$item) {
                if ($continuousAwardDays >= $key) {
                    $item['isSign'] = true;
                }
            }
        } else {
            $continuousAwardDays = bcmod($continuousSignDays, 7);
            foreach ($rewardInfo as $key => &$item) {
                if ($continuousAwardDays > $key) {
                    $item['isSign'] = true;
                }
            }
        }
        if ($rewardInfo[6]['isSign']) {
            $reward = (new CMSignReward())->where(['userId'=>$userId,'date'=>date('Y-m-d')])->find();
            if ($reward && $reward->isLottery=1) {
                $rewardInfo[6]['reward'] = 'know';
            }
        }
        return [
            'todayIsSign' => $todayIsSign,
            'continuousSignDays' => $continuousSignDays,
            'rewardInfo' => $rewardInfo,
        ];
    }

    /**
     * 微信授权登录
     * 获取access_token凭证
     * */
    public function getAccessToken($appId, $secret, $code)
    {
        if (empty($appId) || empty($secret) || empty($code)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$appId.'&secret='.$secret.'&code='.$code.'&grant_type=authorization_code';
        $access_token = curl($url, 'post');
        $accessToken = json_decode($access_token, true);
        if (isset($accessToken['errcode'])) {
            throw AE::factory(AE::WECHAT_TOKEN_ERR);
        }
        return $accessToken;
    }

//    /**
//     * 获取access_token凭证
//     * */
//    public function getToken($appId, $secret)
//    {
//        if (empty($appId) || empty($secret)) {
//            throw AE::factory(AE::COM_PARAMS_ERR);
//        }
//        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$appId.'&secret='.$secret;
//        $access_token = json_decode(curl($url, 'post'), true);
//        if (empty($access_token)) {
//            throw AE::factory(AE::WECHAT_TOKEN_ERR);
//        }
//        return $access_token['access_token'];
//    }

    /**
     * 微信小程序授权登录
     * 获取access_token凭证
     * */
    public function getMiniProgramSession($appId, $secret, $code)
    {
        if (empty($appId) || empty($secret) || empty($code)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$appId.'&secret='.$secret.'&js_code='.$code.'&grant_type=authorization_code';
        $access_token = curl($url, 'post');
        $accessToken = json_decode($access_token, true);
        if (empty($accessToken)) {
            throw AE::factory(AE::WECHAT_TOKEN_ERR);
        }
        return $accessToken;
    }

    /**
     * 微信授权登录
     * 获取用户信息
     * */
    public function getUserInfo($access_token, $openid, $zh='zh_CN')
    {
        if (empty($access_token) || empty($openid)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$openid.'&lang='.$zh;
        $userInfo = json_decode(curl($url), true);
        if (isset($userInfo['errcode'])) {
            throw AE::factory(AE::WECHAT_USERINFO_ERR);
        }
        return $userInfo;
    }

    /**
     * 获取优选用户关注信息
     * */
    public function getUserSubscribeInfo($openid, $zh='zh_CN')
    {
        if (empty($openid)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $appid = config('weixin.publicNumber.appid');
        $access_token = getUxuanAccessToken($appid);;
        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$access_token.'&openid='.$openid.'&lang='.$zh;
        $userInfo = json_decode(curl($url), true);
        if (isset($userInfo['errcode'])) {
            throw AE::factory(AE::WECHAT_USERINFO_ERR);
        }
        return $userInfo;
    }

    public function wechatLogin($code, $type) {
        $appId = config('weixin.'.$type.'.appid');
        $secret = config('weixin.'.$type.'.appsecret');
        $access_token = $this->getAccessToken($appId, $secret, $code);
        $wechatInfo = $this->getUserInfo($access_token['access_token'],$access_token['openid']);
        $wechatData['nickname'] = $wechatInfo['nickname'];
        $wechatData['wechatType'] = $type;
        $wechatData['userPhoto'] = $wechatInfo['headimgurl'];;
        $wechatData['openid'] = $access_token['openid'];
        $map['wxUnionId'] = $wechatInfo['unionid'];
        $wechatData['unionid'] = $wechatInfo['unionid'];
        $u = new U();
        $userInfo = $u->getUserInfo($map);
        if ($userInfo) {
            $su = new SU();
            if (empty($userInfo->appOpenId)) $u->updateUserInfo(['userId'=>$userInfo->userId], ['appOpenId'=>$access_token['openid']]);
            $data = $su->afterLogin($userInfo, $type);
//            (new CSCoupons())->sendLotteryCoupons($wechatInfo['unionid'], $userInfo->userId);
            (new CSCoupons())->sendFanliCoupons($wechatInfo['unionid'], $userInfo->userId);
        } else {
            $data['wechat'] = json_encode($wechatData);
        }
        $data['isBind'] = !empty($userInfo) ? 1 : 0;
        return $data;
    }

    public function wechatMiniProgramLogin($code, $encryptedData, $iv, $pid)
    {
        $wechatType = 'miniPrograms';
        $appId = config('weixin.'.$wechatType.'.appid');
        $secret = config('weixin.'.$wechatType.'.appsecret');
        $access_token = $this->getMiniProgramSession($appId, $secret, $code);
        $wp = new WP();
        $mcrypt_decrypt = $wp->decryptData($encryptedData, $appId, $access_token['session_key'], $iv);
        $map['wxUnionId'] = $mcrypt_decrypt->unionId;
        $u = new U();
        $userInfo = $u->getUserInfo($map);
        if ($userInfo) {
            $su = new SU();
            $updateInfo['mpOpenId'] =  $access_token['openid'];
            if (empty($userInfo->pid)) {
                $updateInfo['pid'] =  $pid;
                $updateInfo['bindPidTime'] = date('Y-m-d H:i:s');
            }
            $u->updateUserInfo(['userId'=>$userInfo->userId], $updateInfo);
            $data = $su->afterLogin($userInfo, 'mp');
//            (new CSCoupons())->sendLotteryCoupons($mcrypt_decrypt->unionId, $userInfo->userId);
            (new CSCoupons())->sendFanliCoupons($mcrypt_decrypt->unionId, $userInfo->userId);
        } else {
            $data['openid'] = $access_token['openid'];
            $data['unionid'] = $mcrypt_decrypt->unionId;
        }
        $data['isBind'] = !empty($userInfo) ? 1 : 0;

        return $data;
    }

    public function wechatH5Authorize($wxOpenId, $wxUnionId)
    {
        $map['wxUnionId'] = $wxUnionId;
        $u = new U();
        $userInfo = $u->getUserInfo($map);
        if ($userInfo) {
            $su = new SU();
            if (empty($userInfo->wxOpenId)) $u->updateUserInfo(['userId'=>$userInfo->userId], ['wxOpenId'=>$wxOpenId]);
            $data = $su->afterLogin($userInfo, 'h5');
//            (new CSCoupons())->sendLotteryCoupons($wxUnionId, $userInfo->userId);
            (new CSCoupons())->sendFanliCoupons($wxUnionId, $userInfo->userId);
        }
        $data['isBind'] = !empty($userInfo) ? 1 : 0;
        return $data;
    }
}