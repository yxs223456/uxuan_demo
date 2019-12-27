<?php
namespace addons\reward\controller;

use think\addons\Controller;
use addons\reward\model\Rewards as M;
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
 * app满就送接口插件
 */
class Apis extends Controller{
    /**
     * 商品促销页面【商品详情】
     */
    public function goodsPromotionDetail(){
        $goodsId = (int)input('goodsId');
        $shopId = (int)input('shopId');
        $m = new M();
        $rs = $m->getAvailableRewards($shopId,$goodsId);
        if(!empty($rs)){
            $rewardDesc = '';
            foreach($rs['json'] as $k=>$v){
                $rewardDesc = "消费满{$v['orderMoney']}元 - ";
                if($v['favourableJson']['chk0'])$rewardDesc .= "减￥{$v['favourableJson']['chk0val']}、\n";
                if($v['favourableJson']['chk1'])$rewardDesc .= "送赠品【{$v['favourableJson']['chk1val']['text']}】、\n";
                if($v['favourableJson']['chk2'])$rewardDesc .= "免邮费、\n";
                if(WSTConf('WST_ADDONS.reward') && $v['favourableJson']['chk3'])$rewardDesc .= "送{$v['favourableJson']['chk3val']['text']}优惠券";
            }  
            return json_encode(WSTReturn('ok',1,['rewardTitle'=>$rs['rewardTitle'],'rewardDesc'=>$rewardDesc]));
        }
        return json_encode(WSTReturn('error',-1));
    }
}