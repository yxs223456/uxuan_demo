<?php
namespace wstmart\app\controller;

use wstmart\common\model\GoodsAppraises as M;
use wstmart\app\service\Users as ASUsers;
use wstmart\common\exception\AppException as AE;
use wstmart\common\service\GoodsAppraises as GA;

/**
 * 评价控制器
 */
class GoodsAppraises extends Base{
    // 前置方法执行列表
    protected $beforeActionList = [
        'checkAuth'  =>  ['except'=>'getbyid,apprinfo'],// 只要访问only下的方法才才需要执行前置操作
    ];
    protected $openAction = [
        'getbyid',
        'add',
        'addnew',
        'apprinfo',
    ];
	/**
	* 根据商品id评论
	*/
	public function getById(){
        $goodsId = (int)getInput('goodsId');
        $offset = (int)getInput('offset', 1);
        $pageSize = (int)getInput('pageSize', 5);
        if (checkInt($goodsId, false)==false || !checkInt($offset, false) || !checkInt($pageSize, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $rs = (new GA())->goodsAppraiseList($goodsId, $offset, $pageSize);
//		if(isset($rs['data']['data'])){
//			foreach($rs['data']['data'] as $k=>$v){
//				if(isset($v['images'])){
//					$imgs = explode(',',$v['images']);
//					foreach($imgs as $k2=>$v2){
//						$imgs[$k2] = WSTImg($v2,3);
//					}
//					$rs['data']['data'][$k]['images'] = $imgs;
//				}
//			}
//		}
		//$rs['domain'] = $this->domain();
		return $this->shopJson($rs);
	}
	/**
	* 根据订单id,用户id,商品id获取评价
	*/
	public function getAppr(){
		$m = model('GoodsAppraises');
		
		$userId = (int)model('app/index')->getUserId();

		$rs = $m->getAppr($userId);
		// 删除无用字段
		unset($rs['data']['shopId']);
		unset($rs['data']['shopReply']);
		unset($rs['data']['isShow']);
		unset($rs['data']['dataFlag']);
		unset($rs['data']['replyTime']);
		if(!empty($rs['data']['images'])){
			$imgs = explode(',',$rs['data']['images']);
			foreach($imgs as $k=>$v){
				$imgs[$k] = WSTImg($v,1);
			}
			$rs['data']['images'] = $imgs;
		}
		return json(WSTReturn($rs));
	}
	/**
	* 添加评价
	*/
	public function add(){
		$m = new M();
		$userId = model('app/index')->getUserId();
		$rs = $m->add((int)$userId);
		return json_encode($rs);
	}

    /**
     * 添加评价
     */
    public function addNew(){

        $userId = ASUsers::getUserByCache()['userId'];
        //检测订单是否有效
        $orderNo = getInput('orderNo');
        // 没有传order_goods表的id
        $goodsScore = (int)getInput('goodsScore', 5);
        $timeScore = (int)getInput('timeScore', 5);
        $content = getInput('content');
        $images = getInput('images', '');
        if (empty($orderNo)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        if (empty($goodsScore) || empty($timeScore)) {
            throw AE::factory(AE::GOODS_APPRAISES_STAR_EMPTY);
        }
        if (empty($content)) {
            throw AE::factory(AE::GOODS_APPRAISES_CONTENT_EMPTY);
        }
        if(isset($content)){
            if(!WSTCheckFilterWords($content,WSTConf("CONF.limitWords"))){
                throw AE::factory(AE::GOODS_APPRAISES_CONTENT_ERROR);
            }
        }
        $rs = (new GA())->insertGoodsAppraises($userId, $orderNo, $goodsScore, $timeScore, $content, $images);
        return $this->shopJson($rs);
    }

    /**
     * 评价详情
     */
    public function apprInfo(){
        $apprId = (int)getInput('post.apprId');
        if(false == checkInt($apprId, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $rs = (new GA())->goodsAppraisesInfo($apprId);
        return $this->shopJson($rs);
    }
}
