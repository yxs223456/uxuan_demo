<?php
namespace wstmart\app\controller;
use wstmart\common\model\Areas as M;
/**
 * 地区控制器
 */
class Areas extends Base{
    protected $openAction = [
        'listquery'
    ];

	/**
	 * 列表查询
	 */
    public function listQuery(){
        $pid = getInput('post.parentId');
        $m = new M();
        $rs = $m->listQuery($pid);
        return $this->shopJson($rs);
    }
}
