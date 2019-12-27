<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/8/31
 * Time: 10:36
 */

namespace wstmart\admin\model;

use think\db;
use wstmart\common\exception\AppException as AE;

class VersionUps extends Base
{
    public function pageQuery()
    {
        return $this->where('dataFlag',1)->order('createTime asc')->paginate(input('limit/d'));
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
        $where['versionNumber'] = $data['versionNumber'];
        $where['type'] = $data['type'];
        $rs = $this->getInfo($where);
        if ($rs) return WSTReturn("版本号不能重复", -1);
        $data['createTime'] = date('Y-m-d H:i:s');
        $this->allowField(['type','path','versionNumber','upgradeType','text','dataFlag','createTime'])->save($data);
        return WSTReturn("新增成功", 1);

    }

    public function getInfo($where)
    {
        $rs = $this->where($where)->find();
        return $rs;
    }

    public function edit($data)
    {
        $id = $data['id'];
        unset($data['id']);
        $where['versionNumber'] = $data['versionNumber'];
        $where['type'] = $data['type'];
        $rs = $this->getInfo($where);
        if ($rs && $rs['id']!=$id) return WSTReturn("版本号不能重复", -1);
        $this->where('id',$id)->update($data);
        return WSTReturn("修改成功", 1);
    }


    public function del($id)
    {
        $rs = $this->where('id', $id)->delete();
        if ($rs==1) {
            return WSTReturn("刪除成功", 1);
        } else {
            return WSTReturn("刪除失敗", -1);
        }
    }

}