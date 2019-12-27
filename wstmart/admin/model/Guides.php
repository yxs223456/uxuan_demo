<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/8/9
 * Time: 19:39
 */
namespace wstmart\admin\model;

use think\db;
use wstmart\admin\validate\Guides as validate;

class Guides extends Base
{
    public function pageQuery()
    {
        return $this->where('dataFlag',1)->order('createTime asc')->paginate(input('limit/d'));
    }

    /**
     * 显示隐藏
     */
    public function setToggle()
    {
        $guideId = input('post.catId/d');
        $isShow = input('post.isShow/d');
        $result = $this->where(['guideId'=>$guideId])->setField("status", $isShow);
        if(false !== $result){
            return WSTReturn("设置成功", 1);
        }else{
            return WSTReturn($this->getError(),-1);
        }
    }

    /**
     * 根据ID获取
     */
    public function getById($guideId)
    {
        $obj = null;
        if($guideId>0){
            $obj = $this->get(['guideId'=>$guideId,"dataFlag"=>1]);
        }
        return $obj;
    }

    public function add($data)
    {
        $data['createTime'] = date('Y-m-d H:i:s');
        $data["dataFlag"] = 1;
        $isRepeat = $this->where(['sort'=>$data['sort'],'dataFlag'=>1])->find();
        if ($isRepeat) return WSTReturn('排序号不能重复',-1);
        $validate = new validate();
        if(!$validate->scene('add')->check($data))return WSTReturn($validate->getError());
        $result = $this
            ->allowField(['type','path','link','proportion','accessType','targetChannel','status','dataFlag','sort','createTime','startTime','endTime'])
            ->save($data);
        if(false !== $result){
            return WSTReturn("新增成功", 1);
        }else{
            return WSTReturn($this->getError(),-1);
        }
    }

    public function edit($data)
    {
        $id = $data['guideId'];
        WSTUnset($data,'createTime');
        $validate = new validate();
        if(!$validate->scene('edit')->check($data))return WSTReturn($validate->getError());
        $this->allowField(true)->save($data,['guideId'=>$id]);
        return WSTReturn("编辑成功", 1);
    }

    public function getGuideInfo($where)
    {
        $info = $this->where($where)->order('sort asc')->select()->toArray();
        return $info;
    }

    public function getGuideImage($where)
    {
        $info = $this->where($where)->order('createTime desc')->limit(0,1)->select()->toArray();
        return $info;
    }

    /**
     * 删除
     */
    public function del($id){
       $data['dataFlag'] = 0;
       $rs = $this->where('guideId', $id)->update($data);
       if ($rs==1) {
           return WSTReturn("刪除成功", 1);
       } else {
           return WSTReturn("刪除失敗", -1);
       }
    }
}