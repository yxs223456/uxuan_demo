<?php
namespace wstmart\common\model;
use think\Db;
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
 * 积分业务处理器
 */
class UserScores extends Base{
	protected $pk = 'scoreId';

	public function getUserScore(array $map)
    {
        $score = $this->where($map)->find();
        return $score;
    }

    public function getCount(array $map)
    {
        $count = $this->where($map)->count();
        return $count;
    }

    public function getList($userId, $offset, $pageSize)
    {
        $scores = $this
            ->where('userId', $userId)
            ->field('score,dataType,scoreType,createTime')
            ->order('scoreId','desc')
            ->limit(($offset - 1) * $pageSize, $pageSize)
            ->select()->toArray();
        return $scores;
    }

    /**
     * 获取领取U币的轮播数据
     */
    public function scoreCarousel($limit)
    {
        $data = $this->alias('us')
            ->leftJoin('users u', 'u.userId=us.userId')
            ->where('us.scoreType', 1)
            ->field('us.score,us.createTime,u.nickname,u.userPhone,u.userPhoto')
            ->order('us.scoreId','desc')
            ->limit(0, $limit)
            ->select()->toArray();
        return $data;
    }

     /**
      * 获取列表
      */
      public function pageQuery($userId){
      	  $type = (int)input('post.type');
          $where = ['userId'=>(int)$userId];
          if($type!=-1)$where['scoreType'] = $type;
          $page = $this->where($where)->order('scoreId desc')->paginate()->toArray();
          foreach ($page['data'] as $key => $v){
          	  $page['data'][$key]['dataSrc'] = WSTLangScore($v['dataSrc']);
          }
          return $page;
      }

      /**
       * 新增记录
       */
      public function add($score,$isAddTotal = false){
      	$score['createTime'] = date('Y-m-d H:i:s');
      	$this->create($score);
      	$user = model('common/users')->get($score['userId']);
      	if($score['scoreType']==1){
      		$user->userScore = $user->userScore + $score['score'];
      		if($isAddTotal)$user->userTotalScore = $user->userTotalScore+$score['score'];
      	}else{
      		$user->userScore = $user->userScore - $score['score'];
      	}
      	$userinfo = session('WST_USER');
      	$userinfo['userScore'] = $user->userScore;
      	session('WST_USER',$userinfo);
      	$user->save();
      }
      
      /**
       *签到获得积分
       */
      public function signScore($userId){
      	$time = date('Y-m-d');
      	$frontTime = date("Y-m-d",strtotime("-1 day"));
      	if(WSTConf('CONF.signScoreSwitch')==0)return WSTReturn("签到失败");
      	$userscores = $this->where(["userId"=>$userId,"dataSrc"=>5,])->order('createTime desc')->find();
      	if(!$userscores || date("Y-m-d",strtotime($userscores['createTime']))!=$time){
      		$rs = Db::name('users')->where(["userId"=>$userId])->field('userScore')->find();
      		$days = $score = 0;
      		$days = (date("Y-m-d",strtotime($userscores['createTime']))==$frontTime)?($userscores['dataId']==30)?$userscores['dataId']:$userscores['dataId']+1:1;
      		$signScore = explode(",",WSTConf('CONF.signScore'));
      		if($signScore[0]!=0){
      			$score = $signScore[$days-1];
      		}
      		$data['totalScore'] = $rs['userScore'] + $score;
      		$data['score'] = $score;
      		if($score>0){
      			//添加
      			$userinfo = session('WST_USER');
      			$userinfo['signScoreTime'] = $time;
      			session('WST_USER',$userinfo);
      			$uscore = [];
      			$uscore['userId'] = $userId;
      			$uscore['score'] = $score;
      			$uscore['dataSrc'] = 5;
      			$uscore['dataId'] = $days;
      			$uscore['dataRemarks'] = "连续".$days."天签到，获得积分".$score."个";
      			$uscore['scoreType'] = 1;
      			$this->add($uscore,true);
      			return WSTReturn("签到第".$days."天，获得".$score."个积分",1,$data);
      		}else{
      			return WSTReturn("签到失败");
      		}
      	}else{
      		return WSTReturn("已签到，明天再来");
      	}
      }
    /*
   * 获取用户积分
   */
    public function myScore($userId)
    {
        if (empty($userId)) {
            throw AE::factory(AE::COM_USERS_ERROR);
        }
        $score = DB::name('user_scores')->where('userId', $userId)->value('score');
        return $score;
    }
}
