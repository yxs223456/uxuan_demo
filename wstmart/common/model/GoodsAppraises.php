<?php
namespace wstmart\common\model;
use wstmart\common\validate\GoodsAppraises as Validate;
use wstmart\common\exception\AppException as AE;
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
 * 评价类
 */
use think\Db;
class GoodsAppraises extends Base{
	public function queryByPage(){
		$shopId = (int)session('WST_USER.shopId');

		$where = [];
		$where['g.goodsStatus'] = 1;
		$where['g.dataFlag'] = 1;
		$where['g.isSale'] = 1;
		$c1Id = (int)input('cat1');
		$c2Id = (int)input('cat2');
		$goodsName = input('goodsName');
		if($goodsName != ''){
			$where[] = ['g.goodsName','like',"%$goodsName%"];
		}
		if($c2Id!=0 && $c1Id!=0){
			$where['g.shopCatId2'] = $c2Id;
		}else if($c1Id!=0){
			$where['g.shopCatId1'] = $c1Id;
		}
		$where['g.shopId'] = $shopId;


		$model = model('goods');
		$data = $model->alias('g')
					  ->field('g.goodsId,g.goodsImg,g.goodsName,ga.shopReply,ga.id gaId,ga.replyTime,ga.goodsScore,ga.serviceScore,ga.timeScore,ga.content,ga.images,u.loginName')
					  ->join('__GOODS_APPRAISES__ ga','g.goodsId=ga.goodsId','inner')
					  ->join('__USERS__ u','u.userId=ga.userId','inner')
					  ->where($where)
					  ->paginate()->toArray();
		if($data !== false){
			return WSTReturn('',1,$data);
		}else{
			return WSTReturn($this->getError(),-1);
		}
	}
	/**
	* 用户评价
	*/
	public function userAppraise(){
		$userId = (int)session('WST_USER.userId');

		$where = [];
		$where['g.goodsStatus'] = 1;
		$where['g.dataFlag'] = 1;
		$where['g.isSale'] = 1;


		$where['ga.userId'] = $userId;


		$model = model('goods');
		$data = $model->alias('g')
					  ->field('g.goodsId,g.goodsImg,g.goodsName,ga.goodsScore,ga.serviceScore,ga.timeScore,ga.content,ga.images,ga.shopReply,ga.replyTime,s.shopName,u.userName,o.orderNo')
					  ->join('__GOODS_APPRAISES__ ga','g.goodsId=ga.goodsId','inner')
					  ->join('__ORDERS__ o','o.orderId=ga.orderId','inner')
					  ->join('__USERS__ u','u.userId=ga.userId','inner')
					  ->join('__SHOPS__ s','o.shopId=s.shopId','inner')
					  ->where($where)
					  ->paginate()->toArray();
		if($data !== false){
			return WSTReturn('',1,$data);
		}else{
			return WSTReturn($this->getError(),-1);
		}
	}
 	/**
	* 添加评价
	*/
//	public function add($uId=0){
//		//检测订单是否有效
//		$orderId = (int)input('orderId');
//		$goodsId = (int)input('goodsId');
//		$goodsSpecId = (int)input('goodsSpecId');
//		$orderGoodsId = (int)input('orderGoodsId');
//		// 没有传order_goods表的id
//		if($orderGoodsId==0)return WSTReturn('数据出错,请联系管理员');
//
//		$userId = ((int)$uId==0)?(int)session('WST_USER.userId'):$uId;
//
//		$goodsScore = (int)input('goodsScore');
//		$timeScore = (int)input('timeScore');
//		$serviceScore = (int)input('serviceScore');
//		$content = input('content');
//		if(isset($content)){
//			if(!WSTCheckFilterWords($content,WSTConf("CONF.limitWords"))){
//				return WSTReturn("点评内容包含非法字符");
//			}
//		}
//		$orders = model('orders')->where(['orderId'=>$orderId,'userId'=>$userId,'dataFlag'=>1])->field('orderStatus,orderNo,isAppraise,orderScore,shopId')->find();
//		if(empty($orders))return WSTReturn("无效的订单");
//		if($orders['orderStatus']!=2)return WSTReturn("订单状态已改变，请刷新订单后再尝试!");
//		//检测商品是否已评价
//		$apCount = $this->where(['orderGoodsId'=>$orderGoodsId,'dataFlag'=>1])->count();
//		if($apCount>0)return WSTReturn("该商品已评价!");
//		Db::startTrans();
//		try{
//			//增加订单评价
//			$data = [];
//			$data['userId'] = $userId;
//			$data['goodsSpecId'] = $goodsSpecId;
//			$data['goodsId'] = $goodsId;
//			$data['shopId'] = $orders['shopId'];
//			$data['orderId'] = $orderId;
//			$data['goodsScore'] = $goodsScore;
//			$data['serviceScore'] = $serviceScore;
//			$data['timeScore']= $timeScore;
//			$data['content'] = $content;
//			$data['images'] = input('images');
//			$data['createTime'] = date('Y-m-d H:i:s');
//			$data['orderGoodsId'] = $orderGoodsId;
//			if(empty(WSTConf('CONF.isAppraise'))){
//                $data['isShow'] = 0;
//			}
//			$validate = new Validate;
//			if (!$validate->scene('add')->check($data)) {
//				return WSTReturn($validate->getError());
//			}else{
//				$rs = $this->allowField(true)->save($data);
//			}
//			if($rs !==false){
//				$lastId = $this->id;
//				WSTUseImages(0, $this->id, $data['images']);
//				//增加商品评分
//
//			    if(!empty(WSTConf('CONF.isAppraise'))){
//				$prefix = config('database.prefix');
//				$updateSql = "update ".$prefix."goods_scores set
//				             totalScore=totalScore+".(int)($goodsScore+$serviceScore+$timeScore).",
//				             goodsScore=goodsScore+".(int)$goodsScore.",
//				             serviceScore=serviceScore+".(int)$serviceScore.",
//				             timeScore=timeScore+".(int)$timeScore.",
//				             totalUsers=totalUsers+1,goodsUsers=goodsUsers+1,serviceUsers=serviceUsers+1,timeUsers=timeUsers+1
//				             where goodsId=".$goodsId;
//				Db::execute($updateSql);
//				//增加商品评价数
//				Db::name('goods')->where('goodsId',$goodsId)->setInc('appraiseNum');
//				$tScore['totalScore'] = 0;
//				$tScore['serviceScore'] = 0;
//				$tScore['goodsScore'] = 0;
//				$tScore['timeScore'] = 0;
//				$where2 = [];
//				$where2['shopId'] = $orders['shopId'];
//				$where2['totalUsers'] = 0;
//				Db::name('shop_scores')->where($where2)->update($tScore);
//				//增加店铺评分
//				$updateSql = "update ".$prefix."shop_scores set
//				             totalScore=totalScore+".(int)($goodsScore+$serviceScore+$timeScore).",
//				             goodsScore=goodsScore+".(int)$goodsScore.",
//				             serviceScore=serviceScore+".(int)$serviceScore.",
//				             timeScore=timeScore+".(int)$timeScore.",
//				             totalUsers=totalUsers+1,goodsUsers=goodsUsers+1,serviceUsers=serviceUsers+1,timeUsers=timeUsers+1
//				             where shopId=".$orders['shopId'];
//				Db::execute($updateSql);
//			    }
//				// 查询该订单是否已经完成评价,修改orders表中的isAppraise
//				$ogRs = Db::name('order_goods')->alias('og')
//				   ->join('__GOODS_APPRAISES__ ga','og.orderId=ga.orderId and og.goodsId=ga.goodsId and og.goodsSpecId=ga.goodsSpecId','left')
//				   ->where('og.orderId',$orderId)->field('og.id,ga.id gid')->select();
//				$isFinish = true;
//				foreach ($ogRs as $key => $v){
//					if($v['id']>0 && $v['gid']==''){
//						$isFinish = false;
//						break;
//					}
//				}
//				//订单商品全部评价完则修改订单状态
//				if($isFinish){
//					if(WSTConf("CONF.isAppraisesScore")==1){
//						$appraisesScore = (int)WSTConf('CONF.appraisesScore');
//						if($appraisesScore>0){
//							//给用户增加积分
//							$score = [];
//							$score['userId'] = $userId;
//							$score['score'] = $appraisesScore;
//							$score['dataSrc'] = 1;
//							$score['dataId'] = $orderId;
//							$score['dataRemarks'] = "评价订单【".$orders['orderNo']."】获得积分".$appraisesScore."个";
//							$score['scoreType'] = 1;
//							$score['createTime'] = date('Y-m-d H:i:s');
//
//							model('UserScores')->add($score,true);
//						}
//					}
//					//修改订单评价状态
//					model('orders')->where('orderId',$orderId)->update(['isAppraise'=>1,'isClosed'=>1]);
//				}
//				//发送一条商家信息
//				$tpl = WSTMsgTemplates('ORDER_APPRAISES');
//				$orderGoods = Db::name('order_goods')->where(['orderId'=>$orderId,'goodsId'=>$goodsId,'goodsSpecId'=>$goodsSpecId])->field('goodsName')->find();
//
//	            $shopId = $orders['shopId'];
//	            if( $tpl['tplContent']!='' && $tpl['status']=='1'){
//	                $find = ['${ORDER_NO}','${GOODS}'];
//	                $replace = [$orders['orderNo'],$orderGoods['goodsName']];
//
//	                $msg = array();
//		            $msg["shopId"] = $shopId;
//		            $msg["tplCode"] = $tpl["tplCode"];
//		            $msg["msgType"] = 1;
//		            $msg["content"] = str_replace($find,$replace,$tpl['tplContent']);
//		            $msg["msgJson"] = ['from'=>6,'dataId'=>$lastId];
//		            model("common/MessageQueues")->add($msg);
//	            }
//	            //微信消息
//		        if((int)WSTConf('CONF.wxenabled')==1){
//		            $params = [];
//		            $params['ORDER_NO'] = $orders['orderNo'];
//		            $params['GOODS'] = $orderGoods['goodsName'];
//
//	                $msg = array();
//					$tplCode = "WX_ORDER_APPRAISES";
//					$msg["shopId"] = $shopId;
//		            $msg["tplCode"] = $tplCode;
//		            $msg["msgType"] = 4;
//		            $msg["paramJson"] = ['CODE'=>$tplCode,'URL'=>'','params'=>$params] ;
//		            $msg["msgJson"] = "";
//		            model("common/MessageQueues")->add($msg);
//		        }
//				Db::commit();
//				return WSTReturn('success',1, true);
//			}else{
//                throw AE::factory(AE::GOODSAPPRAISES_INSERT_FAIL);
//			}
//		}catch (\Exception $e) {
//		    Db::rollback();
//            throw AE::factory(AE::GOODSAPPRAISES_INSERT_FAIL);
//	    }
//
//	}

    public function insertGoodsAppraise($userId, $orderGoodsInfo, $goodsScore, $timeScore, $content, $images)
    {
        //增加订单评价
        $data = [];
        $data['userId'] = $userId;
        $data['goodsSpecId'] = $orderGoodsInfo['goodsSpecId'];
        $data['goodsId'] = $orderGoodsInfo['goodsId'];
        $data['shopId'] = $orderGoodsInfo['shopId'];
        $data['orderId'] =$orderGoodsInfo['orderId'];
        $data['goodsScore'] = $goodsScore;
        $data['serviceScore'] = 0;
        $data['timeScore']= $timeScore;
        $data['content'] = $content;
        $data['images'] = $images;
        $data['createTime'] = date('Y-m-d H:i:s');
        $data['orderGoodsId'] = $orderGoodsInfo['id'];

        if(empty(WSTConf('CONF.isAppraise'))){
            $data['isShow'] = 0;
        }
        $validate = new Validate;
         $rs = $this->allowField(true)->save($data);
         if (!$rs) throw AE::factory(AE::DATA_INSERT_FAIL);
//        if (!$validate->scene('add')->check($data)) {
//            return WSTReturn($validate->getError());
//        }else{
//            return $rs = $this->allowField(true)->save($data);
//        }
        return $rs;
    }

    public function insertGoodsAppraiseNum($orderGoodsInfo, $goodsScore, $serviceScore, $timeScore)
    {
        //增加商品评分
        $prefix = config('database.prefix');
        $updateSql = "update ".$prefix."goods_scores set 
                         totalScore=totalScore+".(int)($goodsScore+$serviceScore+$timeScore).",
                         goodsScore=goodsScore+".(int)$goodsScore.",
                         serviceScore=serviceScore+".(int)$serviceScore.",
                         timeScore=timeScore+".(int)$timeScore.",
                         totalUsers=totalUsers+1,goodsUsers=goodsUsers+1,serviceUsers=serviceUsers+1,timeUsers=timeUsers+1
                         where goodsId=".$orderGoodsInfo['goodsId'];
        Db::execute($updateSql);
        //增加商品评价数
        Db::name('goods')->where('goodsId',$orderGoodsInfo['goodsId'])->setInc('appraiseNum');
        $tScore['totalScore'] = 0;
        $tScore['serviceScore'] = 0;
        $tScore['goodsScore'] = 0;
        $tScore['timeScore'] = 0;
        $where2 = [];
        $where2['shopId'] = $orderGoodsInfo['shopId'];
        $where2['totalUsers'] = 0;
        Db::name('shop_scores')->where($where2)->update($tScore);
        //增加店铺评分
        $updateSql = "update ".$prefix."shop_scores set 
                         totalScore=totalScore+".(int)($goodsScore+$serviceScore+$timeScore).",
                         goodsScore=goodsScore+".(int)$goodsScore.",
                         serviceScore=serviceScore+".(int)$serviceScore.",
                         timeScore=timeScore+".(int)$timeScore.",
                         totalUsers=totalUsers+1,goodsUsers=goodsUsers+1,serviceUsers=serviceUsers+1,timeUsers=timeUsers+1
                         where shopId=".$orderGoodsInfo['shopId'];
        Db::execute($updateSql);
    }

	/**
	* 根据商品id取评论
	*/
//	public function getById(){
//		// 处理匿名
//		$anonymous = (int)input('anonymous',1);
//		$goodsId = (int)input('goodsId');
//		$where = ['ga.goodsId'=>$goodsId,
//				  'ga.dataFlag'=>1,
//				  'ga.isShow'=>1];
//		// 筛选条件
//		$type = input('type');
//		$filterWhere = '';
//		switch ($type) {
//			case 'pic':// 晒图
//				$filterWhere[] = ['ga.images','<>',''];
//				break;
//			case 'best':// 好评
//				$filterWhere = "(ga.goodsScore+ga.serviceScore+ga.timeScore)>=15*0.9";
//				break;
//			case 'good':// 中评
//				$filterWhere = "(ga.goodsScore+ga.serviceScore+ga.timeScore)>=15*0.6 and (ga.goodsScore+ga.serviceScore+ga.timeScore)<15*0.9";
//				break;
//			case 'bad':// 差评
//				$filterWhere = "(ga.goodsScore+ga.serviceScore+ga.timeScore)<15*0.6";
//				break;
//		}
//		$rs  = 	$this->alias('ga')
//					 ->field('DISTINCT(ga.id),ga.content,ga.images,ga.shopReply,ga.replyTime,ga.createTime,ga.goodsScore,ga.serviceScore,ga.timeScore,ga.shopId,ga.orderId,s.shopName,u.userPhoto,u.loginName,u.userTotalScore,goodsSpecNames, og.goodsSpecId')
//					 ->join('__USERS__ u','ga.userId=u.userId','left')
//					 ->join('__ORDER_GOODS__ og','og.orderId=ga.orderId and og.goodsId=ga.goodsId','inner')
//					 ->join('__SHOPS__ s','ga.shopId=s.shopId','inner')
//					 ->where($where)
//					 ->where($filterWhere)
//					 ->paginate(input('pagesize/d'))
//					 ->toArray();
//		foreach($rs['data'] as $k=>$v){
//			// 格式化时间
//			$rs['data'][$k]['createTime'] = date('Y-m-d',strtotime($v['createTime']));
//			$rs['data'][$k]['goodsSpecNames'] = str_replace('@@_@@','，',$v['goodsSpecNames']);
//			// 总评分
//			$rs['data'][$k]['avgScore'] = ceil(($v['goodsScore'] + $v['serviceScore'] + $v['timeScore'])/3);
//			if($anonymous){
//				$start = floor((strlen($v['loginName'])/2))-1;
//				$rs['data'][$k]['loginName'] = substr_replace($v['loginName'],'***',$start,3);
//			}
//			//获取用户等级
//			$rrs = WSTUserRank($v['userTotalScore']);
//			$rs['data'][$k]['userTotalScore'] = $rrs['userrankImg'];
//			$rs['data'][$k]['rankName'] = empty($rrs['rankName'])?' ':$rrs['rankName'];
//			if ($v['goodsSpecId']>0) {
//                $rs['data'][$k]['goodsSpec'] = $this->getGoodsSpecId($v['goodsSpecId']);
//            } else {
//                $rs['data'][$k]['goodsSpec'] = '';
//            }
//            unset($rs['data'][$k]['goodsSpecId']);
//		}
//		// 获取该商品 各评价数
//		$eachApprNum = $this->getGoodsEachApprNum($goodsId);
//		$rs['bestNum'] = $eachApprNum['best'];
//		$rs['goodNum'] = $eachApprNum['good'];
//		$rs['badNum'] = $eachApprNum['bad'];
//		$rs['picNum'] = $eachApprNum['pic'];
//		$rs['sum'] = $eachApprNum['sum'];
//
//		if($rs!==false){
//			return WSTReturn('',1,$rs);
//		}else{
//			return WSTReturn($this->getError(),-1);
//		}
//	}

//    public function getAppraiseList($goodsId, $type, $offset=1, $pagesize=5){
//        $where = ['ga.goodsId'=>$goodsId, 'ga.dataFlag'=>1, 'ga.isShow'=>1];
//        $filterWhere = '';
//        switch ($type) {
//            case 'pic':// 晒图
//                $filterWhere[] = ['ga.images','<>',''];
//                break;
//            case 'best':// 好评
//                $filterWhere = "(ga.goodsScore+ga.serviceScore+ga.timeScore)>=15*0.9";
//                break;
//            case 'good':// 中评
//                $filterWhere = "(ga.goodsScore+ga.serviceScore+ga.timeScore)>=15*0.6 and (ga.goodsScore+ga.serviceScore+ga.timeScore)<15*0.9";
//                break;
//            case 'bad':// 差评
//                $filterWhere = "(ga.goodsScore+ga.serviceScore+ga.timeScore)<15*0.6";
//                break;
//        }
//        $rs  = 	$this->alias('ga')
//            ->field('DISTINCT(ga.id),ga.content,ga.images,ga.shopReply,ga.goodsScore,ga.replyTime,ga.createTime,s.shopName,u.userPhoto,u.loginName,u.userTotalScore,og.goodsSpecNames')
//            ->join('__USERS__ u','ga.userId=u.userId','left')
//            ->join('__ORDER_GOODS__ og','og.orderId=ga.orderId and og.goodsId=ga.goodsId','inner')
//            ->join('__SHOPS__ s','ga.shopId=s.shopId','inner')
//            ->where($where)
//            ->where($filterWhere);
//        $db = clone $rs;
//        $db1 = clone $rs;
//        $data['total'] = $db1->count();
//        $data['list'] = $db->limit(($offset-1)*$pagesize, $pagesize)->select();
//        if($rs!==false){
//            return $data;
//        }else{
//            throw AE::factory(AE::DATA_GET_FAIL);
//        }
//    }

    public function getAppraiseList($goodsId, $offset=1, $pageSize=5){
        if (!config('web.temp_appraises_open')) {
            $appraiseList = $this->alias('ga')
                ->leftJoin('users u', 'u.userId=ga.userId')
                ->where('ga.goodsId', $goodsId)
                ->where('ga.dataFlag', 1)
                ->where('ga.isShow', 1)
                ->field('u.userPhoto,u.nickname,u.userPhone,ga.goodsScore,ga.content,ga.images,ga.createTime')
                ->order('id', 'desc')
                ->limit(($offset-1)*$pageSize, $pageSize)
                ->select();
        } else {
            $appraiseList = $this->alias('ga')
                ->leftJoin('users u', 'u.userId=ga.userId')
                ->field('userPhoto,nickname,userPhone,goodsScore,content,images,ga.createTime')
                ->where('ga.goodsId', $goodsId)
                ->where('ga.dataFlag', 1)
                ->where('ga.isShow', 1)
                ->union(function ($query) use ($goodsId ){
                    $query->name('goods_temp_appraises')
                        ->where('goodsId', $goodsId)
                        ->where('dataFlag', 1)
                        ->where('isShow', 1)
                        ->field('userPhoto,nickname,nickname userPhone,goodsScore,content,images,createTime');
                }, true)
                ->order('createTime', 'desc')
                ->limit(($offset-1)*$pageSize, $pageSize)
                ->select();
        }
        return $appraiseList;
    }

	/**
	* 根据商品id获取各评价数
	*/
//	public function getGoodsEachApprNum($goodsId){
//		$rs = $this->field('(goodsScore+timeScore+serviceScore) as sumScore')->where(['dataFlag'=>1,'isShow'=>1,'goodsId'=>$goodsId])->select();
//		$data = [];
//		$best=0;
//		$good=0;
//		$bad=0;
//		foreach($rs as $k=>$v){
//			$sumScore = $v['sumScore'];
//			// 计算好、差评数
//			if($sumScore >= 15*0.9){
//				++$best;
//			}else if($sumScore < 15*0.6){
//				++$bad;
//			}
//		}
//		$data['best'] = $best;
//		$data['bad'] = $bad;
//		$data['good'] = count($rs)-$best-$bad;
//		// 晒图评价数
//		$data['pic'] = $this->where([['images','<>',''],['goodsId','=',$goodsId],['isShow','=',1],['dataFlag','=',1]])->count();
//		// 总评价数
//		$data['sum'] = $this->where(['dataFlag'=>1,'isShow'=>1,'goodsId'=>$goodsId])->count();
//		return $data;
//	}

    public function getGoodsEachApprNum($goodsId){
        //$rs = $this->field('(goodsScore+timeScore+serviceScore) as sumScore')->where(['dataFlag'=>1,'isShow'=>1,'goodsId'=>$goodsId])->select();
        $rs = $this->field('(goodsScore) as sumScore')->where(['dataFlag'=>1,'isShow'=>1,'goodsId'=>$goodsId])->select();
        $totalScore = 0;
        foreach($rs as $k=>$v){
            $totalScore += $v['sumScore'];
        }
        // 总评价数
        $sum = $this->where(['dataFlag'=>1,'isShow'=>1,'goodsId'=>$goodsId])->count();
        if ($sum>0) {
            $avgScore = bcdiv($totalScore, $sum, 1);
        } else {
            $avgScore = 0;
        }
        return $avgScore;
    }

    /**
     * 根据商品id获取规格
     */
    public function getGoodsSpecId($goodsSpecId)
    {
        $specIds = DB::name('goods_specs')->field('specIds')
            ->where('id', '=', $goodsSpecId)
            ->where('dataFlag', '=', 1)
            ->find();
        $specArr = explode(':', $specIds['specIds']);
        $arr = [];
        $str = '';
        foreach ($specArr as $item) {
            $arr=$this->getSpecItem($item);
            $str.=$arr['itemName'].',';
        }
        return $str;
    }

    /**
     * 获取规格值
     */
    public function getSpecItem($specItemId)
    {
        $specItem = DB::name('spec_items')->field('itemName')
            ->where('itemId', $specItemId)
            ->find();
        return $specItem;
    }

	/**
	* 商家回复评价
	*/
	public function shopReply(){
		$id = (int)input('id');
		$data['shopReply'] = input('reply');
		$data['replyTime'] = date('Y-m-d');
		$rs = $this->where('id',$id)->update($data);
		if($rs !== false){
			return WSTReturn('回复成功',1);
		}else{
			return WSTReturn('回复失败',-1);
		}

	}

    /**
     * 评价详情
     */
	public function apprinfo($apprId){
        $arrInfo = DB::name('goods_appraises')->alias('ga')->field('ga.content,ga.images,ga.createTime,u.userName,u.userPhoto')
            ->join('users u', 'ga.userId=u.userId', 'left')
            ->where('id', $apprId)
            ->find();
        if (!$arrInfo) throw AE::factory(AE::DATA_GET_FAIL);
        return $arrInfo;
    }
}
