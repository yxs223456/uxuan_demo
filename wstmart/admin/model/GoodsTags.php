<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/9/21
 * Time: 10:15
 */

namespace wstmart\admin\model;


class GoodsTags extends Base
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
        $id = input('post.id/d');
        $isShow = input('post.isShow/d');
        $result = $this->where(['id'=>$id])->setField("isShow", $isShow);
        if(false !== $result){
            return WSTReturn("设置成功", 1);
        }else{
            return WSTReturn($this->getError(),-1);
        }
    }

    /**
     * 根据ID获取
     */
    public function getById($id)
    {
        $obj = null;
        if($id>0){
            $obj = $this->get(['id'=>$id,"dataFlag"=>1]);
        }
        return $obj;
    }

    public function add($data)
    {
        $data['createTime'] = date('Y-m-d H:i:s');
        $data["dataFlag"] = 1;
        $isRepeat = $this->where(['name'=>$data['name'],'dataFlag'=>1])->find();
        if ($isRepeat) return WSTReturn('标签名已存在',-1);
        $result = $this->allowField(['name','tid','weight','isShow','dataFlag','createTime'])->save($data);
        if(false !== $result){
            return WSTReturn("新增成功", 1);
        }else{
            return WSTReturn($this->getError(),-1);
        }
    }

    public function edit($data)
    {
        $id = $data['id'];
        WSTUnset($data,'createTime');
        $isRepeat = $this->where(['name'=>$data['name'],'dataFlag'=>1])->where('id', '<>', $id)->find();
        if ($isRepeat) return WSTReturn('标签名已存在',-1);
        $this->allowField(true)->save($data,['id'=>$id]);
        return WSTReturn("编辑成功", 1);
    }

    /**
     * 删除
     */
    public function del($id){
        $data['dataFlag'] = -1;
        $rs = $this->where('id', $id)->update($data);
        if ($rs==1) {
            return WSTReturn("刪除成功", 1);
        } else {
            return WSTReturn("刪除失敗", -1);
        }
    }
}