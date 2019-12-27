<?php
namespace wstmart\app\controller;
use wstmart\app\model\Articles as M;
use wstmart\common\service\News as N;
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
 * 新闻控制器
 */
class News extends Base{
    protected $beforeActionList = [
        'checkAuth' => ['only'=>'getallnewslist,getsystemnews']
    ];
    protected $openAction = [
        'getallnewslist',
        'getsystemnews',
    ];
    /**
    * 获取商城快讯列表
    */
    public function getNewsList(){
    	$m = new M();
    	$data = $m->getArticles();
    	foreach($data['data'] as $k=>$v){
    		$data['data'][$k]['articleContent'] = strip_tags(html_entity_decode($v['articleContent']));
            $data['data'][$k]['createTime'] = date('Y-m-d',strtotime($data['data'][$k]['createTime']));
    	}
    	echo(json_encode(WSTReturn('success',1,$data)));die;
    }
    /**
    * 查看详情
    */
    public function getNews(){
    	$m = new M();
    	$data = $m->getNewsById();
        if(empty($data)){
            die('文章不存在');
        }
        unset($data['articleContent']);
        $data['createTime'] = date('Y-m-d',strtotime($data['createTime']));
        $data['domain'] = $this->domain();
        echo(json_encode(WSTReturn('success',1,$data)));die;
    }
    public function geturlNews(){
    	$m = new M();
    	$data = $m->getNewsById();
    	$data['articleContent']=htmlspecialchars_decode($data['articleContent']);
    	echo '<!DOCTYPE html><html><body><style>img{width:100%}</style>'.$data['articleContent'].'<script>window.onload=function(){window.location.hash = 1;document.title = document.body.clientHeight;}</script></body></html>';
    }
    /**
     * 点赞
     */
    public function like(){
        $m = new M();
        $data = $m->like();
        echo(json_encode($data));
    }
     public function getChild(){
         $m = new M();
         $data = $m->getChildInfos();
         echo(json_encode(WSTReturn('success','1',$data)));die;
    }

    public function getSystemNews()
    {
        $newsType = getInput('newsType');
        $offset = getInput('offset', 1);
        $pageSize = getInput('pageSize', 10);
        $rs = (new N())->getSystemNews($newsType,$offset, $pageSize);
        return $this->shopJson($rs);
    }

    public function getAllNewsList()
    {
        $rs = (new N())->getAllNewsList();
        return $this->shopJson($rs);
    }
}
