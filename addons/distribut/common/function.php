<?php
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
 */
use think\Db;
/**
* 取出分销商品
* @_field 需要取的字段。
* @extra 需要额外取的字段
* @num
*/
function distribut_list($field='',$extra=[],$num=4){
	$where = [];
	$where['goodsStatus'] = 1;
	$where['dataFlag'] = 1;
	$where['isSale'] = 1;
	$where['isDistribut'] = 1;

	$_field = array_merge(['goodsName','goodsImg','goodsId','shopPrice'],$extra);
	if($field!='')$_field=$field;
	$rs = Db::name("goods")->alias('g')
			->where($where)
			->field($_field)
			->order("goodsId asc")
			->limit($num)
	        ->select();
	return $rs;
}
