<?php
namespace wstmart\app\model;
use wstmart\app\model\Shops;
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
 * 商城消息
 */
class Messages extends Base{
   /**
    * 获取列表
    */
    public function pageQuery(){
         $userId = $this->getUserId();
         $where = ['receiveUserId'=>(int)$userId,'dataFlag'=>1];
         $page = model('Messages')->where($where)
                                  ->field('id,msgContent,msgStatus')
                                  ->order('msgStatus asc,id desc')
                                  ->paginate()
                                  ->toArray();
         foreach ($page['data'] as $key => $v){
         	$page['data'][$key]['msgContent'] = str_replace(['&nbsp;',"\n","\r","\t"],' ',trim(strip_tags(htmlspecialchars_decode($v['msgContent']))));
         }
         return $page;
    }
   /**
    *  获取某一条消息详情
    */
    public function getById(){
    	$userId = $this->getUserId();
        $id = (int)input('msgId');
        $data = $this->field('createTime,msgContent,msgStatus')->where(['id'=>$id,'receiveUserId'=>$userId,'dataFlag'=>1])->find();
        if(!empty($data)){
          $data['msgContent'] = htmlspecialchars_decode($data['msgContent']);
          if($data['msgStatus']==0)
            model('Messages')->where('id',$id)->setField('msgStatus',1);
        }
        return $data;
    }
    /**
    * 批量删除
    */
    public function batchDel(){
    	$userId = $this->getUserId();
        $ids = input('ids');
        $data = [];
        $data['dataFlag'] = -1;
        $result = $this->update($data,[['id','in',$ids],['receiveUserId','=',$userId]]);
        if(false !== $result){
            return WSTReturn("删除成功", 1);
        }else{
            return WSTReturn($this->getError(),-1);
        }
    }

    
}
