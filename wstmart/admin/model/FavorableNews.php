<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/10/11
 * Time: 18:13
 */

namespace wstmart\admin\model;

use think\Session;
use think\Db;

class FavorableNews extends Base
{
    public function pageQuery()
    {
        return $this->where(['dataFlag'=>1, 'newsType'=>1])->order('createTime asc')->paginate(input('limit/d'));
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
        $data['newsType'] = 1;
        $data['adminId'] = session('WST_STAFF.staffId');
        WSTUnset($data, 'id');
        Db::startTrans();
        try{
            //WSTRecordImages($data['favorablenewsPath'], 1);
            $result = $this->allowField(true)->insertGetId($data);
            if(false !== $result){
                WSTClearAllCache();
                //$id = $this->id;
                //启用上传图片
                //WSTUseImages(1, $result, $data['favorablenewsPath']);
                Db::commit();
                return WSTReturn("新增成功", 1);
            }else{
                return WSTReturn($this->getError(),-1);
            }
        }catch (\Exception $e) {
            Db::rollback();
        }
        return WSTReturn("新增失败", -1);
    }

    public function edit($data)
    {
        $data['adminId'] = session('WST_STAFF.staffId');
        $id = $data['id'];
        WSTUnset($data,'id');
        Db::startTrans();
        try{
            //WSTUseImages(1, (int)$id, $data['favorablenewsPath'], 'favorablenewsPath', 'favorablenewsPath');
            $result = $this->allowField(true)->save($data,['id'=>$id]);
            if(false !== $result){
                WSTClearAllCache();
                Db::commit();
                return WSTReturn("编辑成功", 1);
            }else{
                return WSTReturn($this->getError(),-1);
            }
        }catch (\Exception $e) {
            Db::rollback();
        }
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