<?php
namespace wstmart\app\controller;

use wstmart\app\service\Users as AUsers;
use wstmart\app\model\UserScores as M;
use wstmart\common\service\UserScores as CSUS;
use wstmart\common\model\UserScores as CM;
use wstmart\common\exception\AppException as AE;
/**
 * 积分控制器
 */
class UserScores extends Base{
    // 前置方法执行列表
   	protected $beforeActionList = ['checkAuth'];

   	protected $openAction = [
        'getlist',
    ];

   	public function getList()
    {
        $offset = getInput('post.offset');
        $pageSize = getInput('post.pageSize');
        if (!checkInt($offset, false) || !checkInt($pageSize, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $userId = AUsers::getUserByCache()['userId'];
        $rs = (new CSUS())->getList($userId, $offset, $pageSize);
        return $this->shopJson($rs);
    }

	/**
    * 查看
    */
	public function index(){
		$userId = model('app/users')->getUserId();
		$data = model('Users')->getFieldsById($userId,['userScore','userTotalScore']);
		return json_encode(WSTReturn('success',1,$data));
	}
    /**
    * 获取数据
    */
    public function pageQuery(){
        $userId = model('app/users')->getUserId();
        $m = new M();
        $data['list'] = $m->pageQuery($userId);
        return json_encode(WSTReturn('success',1,$data));
    }
    /**
    * 用户签到
    */
    public function sign(){
        $m = new CM();
        $userId = model('app/users')->getUserId();
        $rs = $m->signScore($userId);
        return json_encode($rs);
    }
}
