<?php
namespace wstmart\app\controller;
use wstmart\app\model\CashConfigs as M;
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
 * 提现账号控制器
 */
class Cashconfigs extends Base{
    // 前置方法执行列表
    protected $beforeActionList = [
        'checkAuth',
    ];
    /**
     * 查看提现账号
     */
    public function index(){
    	$data['areas'] =  model('areas')->listQuery(0);
    	$data['banks'] =  model('banks')->listQuery(0);
    	return json_encode(WSTReturn('success',1,$data));
    }
    /**
     * 获取用户数据
     */
    public function pageQuery(){
        $userId = model('app/users')->getUserId();
        $m = new M();
        $money = model('Users')->getFieldsById($userId,['userMoney','rechargeMoney']);
        $data['userMoney'] = (($money['userMoney']-$money['rechargeMoney'])>0)?sprintf('%.2f',($money['userMoney']-$money['rechargeMoney'])):0;
        $data['list'] = $m->pageQuery(0,$userId);
        // 获取后台限制的最低提现金额
        $data['drawCashLimit'] = WSTConf('CONF.drawCashUserLimit');
        return json_encode(WSTReturn('success',1,$data));
    }
    /**
    * 获取记录
    */
    public function getById(){
       $id = (int)input('id');
       $m = new M();
       $data = $m->getById($id);
       return json_encode(WSTReturn('success',1,$data));
    }
    /**
     * 新增
     */
    public function add(){
        $m = new M();
        $rs = $m->add();
        return json_encode($rs);
    }
    /**
     * 编辑
     */
    public function edit(){
        $m = new M();
        $rs = $m->edit();
        return json_encode($rs);
    }
    /**
     * 删除
     */
    public function del(){
        $m = new M();
        $rs = $m->del();
        return json_encode($rs);
    }
}
