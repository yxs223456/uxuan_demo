<?php
namespace addons\bargain\controller;

use think\addons\Controller;
use addons\bargain\model\Admin as M;
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
 * 全民砍价插件
 */
class Admin extends Controller{

    /**
     * 查看砍价商品列表
     */
    public function index(){
        $this->checkAdminPrivileges();
        $this->assign("areaList",model('common/areas')->listQuery(0));
        return $this->fetch("/admin/list");
    }

    /**
     * 查询砍价商品
     */
    public function pageQuery(){
        $this->checkAdminPrivileges();
        $m = new M();
        return WSTGrid($m->pageQuery(1));
    }
    /**
     * 查询待审核砍价商品
     */
    public function pageAuditQuery(){
        $this->checkAdminPrivileges();
        $m = new M();
        return WSTGrid($m->pageQuery(0));
    }

    /**
    * 设置违规商品
    */
    public function illegal(){
        $this->checkAdminPrivileges();
        $m = new M();
        return $m->illegal();
    }
    /**
     * 通过商品审核
     */
    public function allow(){
        $this->checkAdminPrivileges();
        $m = new M();
        return $m->allow();
    }

    /**
     * 删除
     */
    public function del(){
        $this->checkAdminPrivileges();
        $m = new M();
        return $m->del();
    }

    /**
     * 查看参与人
     */
    public function joins(){
        $this->checkAdminPrivileges();
        $this->assign("bargainId",(int)input('bargainId'));
        return $this->fetch("/admin/list_users");
    }
    public function pageyByJoins(){
        $this->checkAdminPrivileges();
        $m = new M();
        return WSTGrid($m->pageyByJoins());
    }
    /**
     * 查看亲友团
     */
    public function showHelps(){
        $this->checkAdminPrivileges();
        $this->assign("bargainId",input('bargainId/d'));
        $this->assign("bargainJoinId",input('bargainJoinId/d'));
        return $this->fetch("/admin/list_helps");
    }
    public function pageByHelps(){
        $this->checkAdminPrivileges();
        $m = new M();
        return WSTGrid($m->pageByHelps());
    }
}