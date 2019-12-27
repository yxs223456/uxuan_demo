<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/10/15
 * Time: 19:26
 */

namespace wstmart\common\model;


class UserNews extends Base
{
    public function insertUserNewsInfo($data)
    {
        return $this->insertGetId($data);
    }

    public function getNewsByUserId($userId, $newsType, $offset=1, $pageSize=10, $order='createTime')
    {
        $rs = $this->where(['userId'=>$userId,'newsType'=>$newsType])
            ->limit(($offset-1)*$pageSize, $pageSize)
            ->order($order, 'desc')->select();
        return $rs;
    }

    public function getNoReadNewNumByUserId($userId, $newsType)
    {
        return $this->where(['userId'=>$userId,'newsType'=>$newsType])->count();
    }

    public function getUserNews($where, $offset=1, $pageSize=10, $order='createTime')
    {
        $rs = $this->where($where);
        $db = clone $rs;
        $db1 = clone $rs;
        $db2 = clone $rs;
        $db2->where('isRead', 0)->update(['isRead'=>1]);
        $data['total'] = $db->count();
        $data['list'] = $db1->field('id, noticeType, title, text, createTime')
            ->order($order, 'desc')->limit(($offset-1)*$pageSize, $pageSize)->select()->toArray();
        $data['offset'] = $offset;
        $data['pageSize'] = $pageSize;
        return $data;
    }

    public function getNewsList($where, $field='*', $order='createTime', $total=false)
    {
        $rs = $this->where($where)->field($field);
        $db_count = clone $rs;
        $db_list = clone $rs;
        $data['isReadTotal'] = $db_count->where('isRead', 0)->count();
        if ($total==false) $data['list'] = $db_list->order($order, 'desc')->limit(1)->find();
        return  $data;
    }
}