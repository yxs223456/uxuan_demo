<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/17
 * Time: 11:26
 */
namespace wstmart\common\model;

class GoodsShareBrowse extends Base
{
    public function getCount($map, $field = '*')
    {
        $count = $this->where($map)->count($field);
        return $count;
    }

    public function getBrowseUv($userId)
    {
        $map = [
            ['sharer', '=', $userId],
            ['browser', '>', 0],
        ];
        $uv = $this->getCount($map, 'distinct(browser)');
        return $uv;
    }

    public function getBrowsePv($userId)
    {
        $map = [
            'sharer' => $userId,
        ];
        $pv = $this->getCount($map);
        return $pv;
    }
}