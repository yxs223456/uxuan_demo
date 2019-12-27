<?php
namespace wstmart\common\model;
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
 * 广告类
 */
class Ads extends Base{
	protected $pk = 'adId';
	public function recordClick(){
		$id = (int)input('id');
		return $this->where("adId=$id")->setInc('adClickNum');
	}

    public function drawAdsPositionList($positiontType, $version, $positionCode)
    {
        $where['a.positionType'] = $positiontType;
        $where['a.version'] = $version;
        $where['ap.positionCode'] = $positionCode;
        $where['a.dataFlag'] = 1;
        $where['a.isShow'] = 1;
       $where[] = ['a.adStartDate', '<', date('Y-m-d').' 00:00:00'];
        $where[] = ['a.adEndDate', '>', date('Y-m-d').' 23:59:59'];
        $list = $this->alias('a')
            ->leftJoin('ad_positions ap', 'a.adPositionId=ap.positionId')
            ->where($where)
            ->field('a.adId,a.adFile,a.adName,a.proportion,a.accessType,a.moduleUrl,adURL,a.adStartDate,a.adEndDate')
            ->order('a.adSort asc')
            ->select();
        return $list;
    }

    public function setIncAdsClicks($adId)
    {
        return $this->where('adId', $adId)->setInc('adClickNum');
    }

    public function getAds($where)
    {
        $info = $this->where($where)->find();
        return $info;
    }
}
