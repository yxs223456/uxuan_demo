<?php
namespace wstmart\admin\model;
use think\Db;
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
 * API业务处理
 */
class Apis extends Base{
	/**
	 * 分页
	 */
	public function pageQuery(){
		$key = input('key');
		$apiType = input('apiType/d',-1);
		$where = [];
		$where[] = ['dataFlag','=',1];
		if($key!='')$where[] = ['apiName|apiDesc','like','%'.input('key').'%'];
		if($apiType!=-1)$where[] = ['apiType','=',$apiType];
		return $this->where($where)
		            ->order('apiSort','desc')
		            ->paginate(input('limit/d'));
	}
	public function getById($id){
		return $this->get(['id'=>$id,'dataFlag'=>1]);
	}
	/**
	 * 新增
	 */
	public function add(){
		$data = input('post.');
		$data['createTime'] = date('Y-m-d H:i:s');
		if($data['apiName']=='')return WSTReturn('接口名不能为空');
		if($data['apiDesc']=='')return WSTReturn('接口说明不能为空');
		WSTUnset($data,'id,dataFlag');
		$result = $this->allowField(true)->save($data);
        if(false !== $result){
        	return WSTReturn("新增成功", 1);
        }
        return WSTReturn('新增失败',-1); 
	} 
    /**
	 * 编辑
	 */
	public function edit(){
		$data = input('post.');
		WSTUnset($data,'createTime,dataFlag');
		if($data['apiName']=='')return WSTReturn('接口名不能为空');
		if($data['apiDesc']=='')return WSTReturn('接口说明不能为空');
		$result = $this->allowField(true)->save($data,['id'=>(int)$data['id']]);
	    if(false !== $result){
	        return WSTReturn("编辑成功", 1);
	    }
        return WSTReturn('编辑失败',-1); 
	}
	/**
	 * 删除
	 */
    public function del(){
	    $id = (int)input('post.id/d');
		$result = $this->setField(['id'=>$id,'dataFlag'=>-1]);
	    if(false !== $result){
	        return WSTReturn("删除成功", 1);
	    }
        return WSTReturn('删除失败',-1);
	}
	/**
	* 修改广告排序
	*/
	public function changeSort(){
		$id = (int)input('id');
		$apiSort = (int)input('apiSort');
		$result = $this->setField(['id'=>$id,'apiSort'=>$apiSort]);
		if(false !== $result){
        	return WSTReturn("操作成功", 1);
        }else{
        	return WSTReturn($this->getError(),-1);
        }
	}
	
}
