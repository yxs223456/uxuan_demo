<?php
namespace wstmart\app\controller;

use wstmart\app\model\GoodsCats as M;
use wstmart\common\helper\Redis;
use wstmart\app\service\Users as ASUser;
use wstmart\common\service\Goods as SG;

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
 * 商品分类控制器
 */
class GoodsCats extends Base {
    protected $openAction = [
        'pagequery',
        'getgoodscats',
        'index',
        'updatecatsort',
    ];

    /**
    * 获取所有一级分类
    */
    public function pageQuery(){
        $catSortName = config('enum.catSort');
        $catSortUserName = config('enum.catSortUser');
        $userId = ASUser::getUserByCache()['userId'];
        $rs = (new SG())->getCatsPageQuery($userId, $catSortName, $catSortUserName);
        return $this->shopJson($this->imageAddDomain($rs));
    }

    public function imageAddDomain($arr)
    {
        foreach ($arr as $k=> $item) {
            $arr[$k]['catImg'] = addImgDomain($item['catImg']);
        }
        return $arr;
    }

    /**
     * 用户自定义分类排序
     * 修改一级分类排序
     */
    public function updateCatSort()
    {
        $userId = ASUser::getUserByCache()['userId'];
        $sg = new SG();
        $rs = $sg->updateUserSort($userId);
        return $this->shopJson($rs);
    }

    /**
    * 获取一级商品分类
    */
    public function getgoodscats(){
        $rs = WSTGoodsCats(0);
        return $this->shopJson($rs);
    }
	/**
     * 列表
     */
    public function index(){
    	$m = new M();
    	$goodsCatList = $m->getGoodsCats();
    	if(!empty($goodsCatList)){
            // 域名
            $goodsCatList['domain'] = url('/','','',true);
            return jsonReturn('success',1,$goodsCatList);
        }
        return jsonReturn('error',-1);
    }
}
