<?php
namespace wstmart\app\controller;

use wstmart\app\model\Apis as M;
use wstmart\app\service\Users as ASUser;
use think\Db;
use wstmart\common\exception\AppException as AE;
use wstmart\common\service\Refund;
use wstmart\app\service\Users as ASUsers;
use wstmart\common\service\Users as CSUsers;
use wstmart\common\service\TaskWelfare as CSTW;
use wstmart\common\service\Pintuan as SPintuan;
use wstmart\app\service\Goods as SGoods;
use wstmart\common\service\Apis as A;
use wstmart\common\service\TaskWelfare;
use wstmart\common\service\WxTemplateNotify as WT;
use wstmart\common\model\Users as CMUsers;
use think\facade\Env;

/**
 * ============================================================================
 * WSTMart多用户商城
 * 版权所有 2016-2066 广州商淘信息科技有限公司，并保留所有权利。
 * 官网地址:http://www.wstmart.net
 * 交流社区:http://bbs.shangtao.net
 * 联系QQ:153289970
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！未经本公司授权您只能在不用于商业目的的前提下对程序代码进行修改和使用；
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * API控制器
 */
class Apis extends Base{
    protected $beforeActionList = [
        'checkAuth' => ['except'=>'lotterysendcoupons,binduxuan,peekaboo,submitfeedback,expresslist,share,uploadpic,cityjson,
        logistics,getreason,addcouponuserstowork,createwxaqrcode'],
    ];
    protected $openAction = [
        'lotterysendcoupons',
        'taskwelfare',
        'goodstags',
        'share',
        'binduxuan',
        'peekaboo',
        'cityjson',
        'logistics',
        'mptemplatedata',
        'mptemplatebatch',
        'uploadpic',
        'getreason',
        'submitfeedback',
        'expresslist',
        'createwxaqrcode',
        'addcouponuserstowork',
        'indexconfiginfo',
        'createwxaqrcode',
    ];


    /**
     * 用户任务中心首页接口
     */
    public function taskWelfare()
    {
        $userId = ASUsers::getUserByCache()['userId'];
        $taskWelfareService = new CSTW();
        $rs = $taskWelfareService->taskIndex($userId);
        return $this->shopJson($rs);
    }

    public function bindUxuan()
    {
        $openId = getInput('get.openId');
        if (empty($openId)) {
            return;
        }
        (new TaskWelfare())->bindUxuan($openId);
    }

    public function goodsTags()
    {
        $userId = ASUsers::getUserByCache()['userId'];
        $rs = (new A())->getUserGoodsTags($userId);
        return $this->shopJson($rs);
    }

    public function lotterySendCoupons()
    {
        $unionId = getInput('unionid');
        $couponIds = getInput('couponIds');

        $couponIds = json_decode($couponIds, true);
        if (empty($unionId)) {
            return;
        }

        $now = time();
        $endTime = date('Y-m-d', strtotime('+30 day'));
        $user = (new CMUsers())->where('wxUnionId', $unionId)->find();

        foreach ($couponIds as $couponId) {
            if (!checkInt($couponId, false)) {
                continue;
            }
            $sendCouponInfo = [
                'unionId' => $unionId,
                'couponId' => $couponId,
            ];
            if (!empty($user)) {
                $sendCouponInfo['isSend'] = 1;
                $sendCouponInfo['sendTime'] = $now;
                $doSendCoupon = [
                    'shopId' => 1,
                    'couponId' => $couponId,
                    'userId' => $user->userId,
                    'isUse' => 0,
                    'createTime' => $now,
                    'endTime' => $endTime,
                ];
                DB::name('coupon_users')->insert($doSendCoupon);
                DB::name('coupons')->where('couponId', $couponId)->setInc('receiveNum', 1);
            }
            DB::name('lottery_send_coupons')->insert($sendCouponInfo);
        }
    }

    public function peekaboo()
    {
        $userQuery = ASUsers::getUserByCache();
        $isHide = (new A())->isHide($userQuery['commonParams']);
        $rs = [
            'isHide' => $isHide,
        ];
        return $this->shopJson($rs);
    }

    public function createWxAqrcode()
    {
        $appid = config('weixin.miniPrograms.appid');
        $accessToken = getUxuanAccessToken($appid);
        $url = 'https://api.weixin.qq.com/cgi-bin/wxaapp/createwxaqrcode?access_token='.$accessToken;
        //$url = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token='.$accessToken;
        try{
            //$goodsArr = ['195','208','204','179','191','209'];
            //$goodsArr = ['205','198','199','197','196','206'];
            //$goodsArr = ['200','203','207','202','201','119'];
            $goodsArr = ['220'];
            for ($i = 0;$i<1; $i++){
                foreach ($goodsArr as $k=>$goodsId) {
                    $this->filterData($i,$k,$goodsId, $url);
                }

            }
            return $this->shopJson(true);
        }catch (\Throwable $e) {
            return $e->getMessage();
        }

    }

    public function filterData($i,$k, $goodsId, $url)
    {
        $name = 'xian';
        $dir = Env::get('root_path').'upload/temp/'.$name.'-'.($i);
        $data['path'] = 'pages/goodsDetails/main?goodsId='.$goodsId.'&pid=666667_'.($i).'&b=0';
       // $data['scene'] = '?goodsId='.$goodsId.'&pid=666666_'.($i+1).'&b=0';
        //$data['page'] = 'pages/goodsDetails/main';
        $code = curl($url, 'post', $data , true);
        if (!file_exists($dir)) {
            mkdir($dir,0777,true);
        }
        $touch = $name.'-'.($i).'-'.($k+1).'-666667_'.($i).'-g_'.$goodsId;
        $dirs = $dir.'/'.$touch.'.png';
        file_put_contents($dirs, $code);
    }

    public function cityJson()
    {
        $webConfig = config('web.');
        $filePath =  $webConfig['image_domain'] . $webConfig['city_file_path'];
        $content = file_get_contents($filePath);
        return $content;
    }

    public function mpTemplateData()
    {
        $user = ASUsers::getUserByCache();
        $type = getInput('post.type');
        $target = getInput('post.target');
        $page = getInput('post.page');
        $formId = getInput('post.formId');
        if (empty($type) || empty($target) || empty($formId)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        if (!array_key_exists($type, config('weixin.template.mp'))) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        if (in_array($type,config('weixin.isNowSendType'))) {
            $wt = new WT();
            $typeFunction = str_replace(' ','',lcfirst(ucwords(str_replace('_',' ',$type))));
            $wt->$typeFunction($user['userId'],$target,$page,$formId);
            return $this->shopJson(true);
        }
        DB::name('mp_template_data')->insert([
            'userId' => $user['userId'],
            'type' => $type,
            'target' => $target,
            'params' => json_encode([
                'page' => $page,
                'form_id' => $formId
            ], JSON_UNESCAPED_UNICODE),
        ]);
        return $this->shopJson(true);
    }

    public function mpTemplateBatch()
    {
        $user = ASUsers::getUserByCache();
        $batch = getInput('post.batch');
        $batchData = json_decode($batch, true);
        if (!is_array($batchData)) {
            return $this->shopJson(true);
        }
        foreach ($batchData as $batchDatum) {
            if (!isset($batchDatum['type']) || !isset($batchDatum['target']) || !isset($batchDatum['page']) ||
                !isset($batchDatum['formId'])) {
                continue;
            }
            DB::name('mp_template_data')->insert([
                'userId' => $user['userId'],
                'type' => $batchDatum['type'],
                'target' => $batchDatum['target'],
                'params' => json_encode([
                    'page' => $batchDatum['page'],
                    'form_id' => $batchDatum['formId']
                ], JSON_UNESCAPED_UNICODE),
            ]);
        }
        return $this->shopJson(true);
    }

    public function logistics()
    {
        $webConfig = config('web.');
        $logistics = $webConfig['logistics'];
        foreach ($logistics as &$logistic) {
            $logistic['icon'] = $webConfig['image_domain'] . $logistic['icon'];
        }
        unset($logistic);
        return $this->shopJson($logistics);
    }
	
    public function index(){
        $m = new M();
        $rs = $m->listQuery();
        $this->assign('list',$rs);
        $this->assign('apiType',(input('apiType/d',0)==1));
    	return $this->fetch("list");
    }

    public function share()
    {
        $target = getInput('post.target');
        $type = getInput('post.type');
        if (!in_array($type, ['goods', 'pintuan', 'bindFans', 'myFavoriteGoods'], true)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        if (in_array($type, ['goods', 'pintuan'], true) && empty($target)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $userId = ASUsers::getUserByCache()['userId'];
        if (in_array($type, ['bindFans', 'myFavoriteGoods'], true) && empty($userId)) {
            throw AE::factory(AE::USER_NOT_LOGIN);
        }
        $rs = [];
        if ($type == 'pintuan') {
            $pintuanService = new SPintuan();
            $rs = $pintuanService->sharePintuan($target, $userId);
        } elseif ($type == 'goods') {
            $goodsService = new SGoods();
            $rs = $goodsService->share($target, $userId);
        } elseif ($type == 'bindFans') {
            $userService = new CSUsers();
            $rs = $userService->shareBindFans($userId);
        } elseif ($type == 'myFavoriteGoods') {
            $userService = new CSUsers();
            $rs = $userService->shareMyFavoriteGoods($userId);
        }
        return $this->shopJson($rs);
    }

    public function uploadPic()
    {
        $data = shopUploadPic(0);
        $rs = [
            'domain' => config('web.image_domain'),
            'path' => $data,
        ];
        return $this->shopJson($rs);
    }

    public function getReason()
    {
        $type = getInput('post.type');
        if (!checkInt($type, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $codeArr = ['1'=>'ORDER_CANCEL','2'=>'ORDER_REJECT','4'=>'REFUND_TYPE', '17'=>'FEEDBACK_TYPE'];
        $type = (isset($codeArr[$type]))?$codeArr[$type]:$type;
        $data = WSTDatas($type);
        if (is_array($data)) {
            $data = array_values($data);
        } else {
            $data = [];
        }
        return $this->shopJson($data);
    }

    public function submitFeedback()
    {
        $feedbackType = getInput('post.feedbackType');
        $userPhone = getInput('post.userPhone');
        $feedback = getInput('post.feedback');
        $images = getInput('post.images', '');
        $userQuery = ASUsers::getUserByCache();
        $userId = $userQuery['userId'];
        $commonParams = $userQuery['commonParams']->toArray();

        $this->recordFeedback($userId, $feedbackType , $userPhone, $feedback, $images, $commonParams);
        return $this->shopJson(true);
    }

    protected function recordFeedback($userId, $feedbackType , $userPhone, $feedback, $images, $commonParams)
    {
        DB::name('feedback')->data([
            'userId' => $userId,
            'feedbackType' => $feedbackType,
            'userPhone' => $userPhone,
            'feedback' => $feedback,
            'images' => $images,
            'clientInfo' => json_encode($commonParams, JSON_UNESCAPED_UNICODE),
        ])->insert();
    }

    /*
     * 快递列表展示
     */
    public function expressList()
    {
        $refund = new Refund();
        $res = $refund->expressList();
        return $this->shopJson($res);
    }

    public function addCouponUsersToWork()
    {
        //$phone = ['18612117163', '15811009665'];
        $userphone = '15726653645，18810134744，17600742700，13718550421，15210487012，13644033921，18801257126，18610665312，15708479069，17621669400，18842660366，18970145987，18161921801，17791419984，13149287582，15991215342，18602931259，13804203348，18065393115，15081376830，18618351562，18862602925，15651885955，15119100315，18513340615，15810671152，15701673525，13671247624，18810408422，15011500090，13811038229，18518133416，13799983009，18511967446，15260226250，13661117965，13810620906，13811332429，13661396280，18601399729，13910634929，13466327105，13910863227，13810001005，15901201841，18600806350，13910693347，18614055509，15810639495，18611056036，13001198972，13910922102，13910687521，13911908664，13810549909，15120041977，18618498660，13311137761，18602106689，18611346057，18810331668，13901250242，17759219931，17717680303，18911352327，13560004026，18611921181，13811187587，13621198703，13466395390，13911355436，13910507183，13501376274，13811098126，18311211922，18600184180，18610448098，18612032209，18817711181，15200843197，13910102403，13925249965，13910403290，15087054814，18501318014，13641332604，18910847255，18911303637，18610669211，18600423031，13711442941';

        $phone = explode('，',$userphone);
        $couponId = 14;
        $coupons = Db::name('coupons')->where('couponId', $couponId)->find();
        $data['shopId'] = $coupons['shopId'];
        $data['couponId'] = $coupons['couponId'];
        $data['isUse'] = 0;
        $data['createTime'] = date('Y-m-d H:i:s');
        $userIds = Db::name('users')->where('userPhone', 'in', $phone)->column('userPhone');
        $aa = array_diff($phone, $userIds);
        //$couponUserarrayId = Db::name('coupon_users')->where('couponId', $couponId)->column('userId');
       // $data['endTime'] = $coupons['endDate'];
//        foreach ($userIds as $k=>$v) {
//            if (in_array($v, $couponUserarrayId)) {
//                continue;
//            }
//            $data['userId'] = $v;
//            $couponUserId = Db::name('coupon_users')->insertGetId($data);
//            if ($couponUserId>0) {
//                Db::name('coupons')->where('couponId', $couponId)->setInc('receiveNum');
//            }
//            echo '添加成功----'.$couponUserId."\n";
//        }


    }

    public function indexConfigInfo()
    {
        $api = new A();
        $res = $api->indexConfigInfo();
        return $this->shopJson($res);
    }

}
