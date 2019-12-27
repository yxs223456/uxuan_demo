<?php
namespace wstmart\app\controller;
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
 * 默认控制器
 */
use think\App;
use think\Controller;
use think\Db;
use wstmart\app\model\AppSession;
use wstmart\common\model\Users as CMUsers;
use wstmart\common\struct\CommonParams as CP;
use wstmart\common\exception\AppException as AE;

class Base extends Controller{
    protected $openAction = [];//可以访问的方法
    /**
     * 前置操作
     * @access protected
     * @param  string $method  前置操作方法名
     * @param  array  $options 调用参数 ['only'=>[...]] 或者['except'=>[...]]
     */
    protected function beforeAction($method, $options = [])
    {
        if (isset($options['only'])) {
            if (is_string($options['only'])) {
                $options['only'] = explode(',', $options['only']);
            }
            foreach ($options['only'] as &$only) {
                $only = trim($only);
            }
            unset($only);
            if (!in_array($this->request->action(), $options['only'])) {
                return;
            }
        } elseif (isset($options['except'])) {
            if (is_string($options['except'])) {
                $options['except'] = explode(',', $options['except']);
            }
            foreach ($options['except'] as &$except) {
                $except = trim($except);
            }
            unset($except);
            if (in_array($this->request->action(), $options['except'])) {
                return;
            }
        }

        call_user_func([$this, $method]);
    }
    protected function initialize()
    {
        parent::initialize();
        preg_domain();
        $action = $this->request->action();
        if (!in_array($action, $this->openAction)) {
            throw AE::factory(AE::COM_URL_ERROR);
        }
        $input = file_get_contents('php://input');
        $GLOBALS['input'] = json_decode($input, true);
        $this->checkShopLogin();
    }

    protected function checkShopLogin()
    {
        $commonParams = new CP(array_merge(getInput('get.'), getInput('post.')));
        $tokenId = $this->request->header('token');
        if (empty($tokenId)) {
            $isLogin = false;
            $userId = null;
            $client = '';
        } else {
            $appSessionModel = new AppSession();
            $userToken = $appSessionModel->getTokenByTokenId($tokenId);
            if (empty($userToken)) {
                $isLogin = false;
                $userId = null;
                $client = '';
            } else {
                $isLogin = true;
                $userId = $userToken['userId'];
                $client = $userToken['type'];
                $clientInfo = [
                    'brand' => $commonParams->brand,
                    'model' => $commonParams->model,
                    'system' => $commonParams->system,
                    'channel' => $commonParams->channel,
                    'version' => $commonParams->version,
                    'xmRegid' => $commonParams->xmRegid,
                ];
                $appSessionModel->update($clientInfo, ['id'=>$userToken['id']]);
                (new CMUsers())->where('userId', $userId)->update(['lastTime'=>date('Y-m-d H:i:s'),'lastIP'=>getClientIp()]);
            }
        }
        $GLOBALS['query'] = [
            'isLogin' => $isLogin,
            'userId' => $userId,
            'client' => $client,
            'commonParams' => $commonParams,
        ];
    }

    /**
     * 域名
     */
    public function domain(){
    	return url('/','','',true);
    }
    /**
	 * 获取验证码
	 */
	public function getVerify(){
		WSTVerify();
	}
    // 权限验证方法
    protected function checkAuth(){
	    if ($GLOBALS['query']['isLogin']) {
	        return true;
        }
        if ($this->request->header('token')) {
            throw AE::factory(AE::USER_TOKEN_ERR);
        }
        throw AE::factory(AE::USER_NOT_LOGIN);
       /* $tokenId = input('tokenId');
        if($tokenId==''){
            $rs = json_encode(WSTReturn('您还未登录',-999));
            die($rs);
        }
        $userId = Db::name('app_session')->where("tokenId='{$tokenId}'")->value('userId');
        if(empty($userId)){
            $rs = json_encode(WSTReturn('登录信息已过期,请重新登录',-999));
            die($rs);
        }*/
    }
    /**
     * 上传图片
     */
    public function uploadPic(){
        return WSTUploadPic(0);
    }
    /**
    * 获取插件状态
    */
    public function getAddonStatus(){
        $addons = ['Auction','Kuaidi','Coupon','Reward','Integral','Groupon','Distribut','Wstim','Thirdlogin'];
        $rs = Db::name('addons')->where('dataFlag',1)->field('name,status')->select();
        $arr = [];
        foreach ($rs as $k=>$v) {
            if(in_array($v['name'], $addons)){
                $arr[$v['name']] = ($v['status']==1);
            }
        }
        if(isset($arr['Thirdlogin']) && $arr['Thirdlogin']==true ){
            $config = Db::name('addons')->where(['dataFlag'=>1,'name'=>'Thirdlogin'])->value('config');
            $config = json_decode($config,true);
            // 获取开启了哪些第三方登录
            $arr['ThirdloginCfg'] = $config['thirdTypes'];
        }
        return json_encode(WSTReturn('ok',1,$arr));
    }

    public function shopJson($data = [], $code = 200, $header = [], $options = [])
    {
        $response = [
            'status' => 1,
            'msg' => 'success',
            'data' => $data,
        ];
        return json($response, $code, $header, $options);
    }
}
