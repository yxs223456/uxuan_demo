<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/9/6
 * Time: 11:05
 */

namespace wstmart\admin\model;


class AuditSwitchs extends Base
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
        $where['channel'] = $data['channel'];
        $where['version'] = $data['version'];
        $rs = $this->getInfo($where);
        if ($rs) {
            return WSTReturn("版本号已存在", -1);
        }
        $this->allowField(['channel','version','versionCode'])->save($data);
        return WSTReturn("新增成功", 1);

    }

    public function getInfo($where)
    {
        $rs = $this->where($where)->where('dataFlag', 1)->find();
        return $rs;
    }

    public function edit($data)
    {
        $id = $data['id'];
        unset($data['id']);
        $where['channel'] = $data['channel'];
        $where['version'] = $data['version'];
        $where[] = ['id', '<>', $id];
        $rs = $this->getInfo($where);
        if ($rs) {
            return WSTReturn("版本号不能重复", -1);
        }
        $this->where('id',$id)->update($data);
        return WSTReturn("修改成功", 1);
    }

    public function del($id)
    {
        $data['dataFlag'] = -1;
        $rs = $this->where('id', $id)->update($data);
        if ($rs==1) {
            return WSTReturn("刪除成功", 1);
        } else {
            return WSTReturn("刪除失敗", -1);
        }
    }
}