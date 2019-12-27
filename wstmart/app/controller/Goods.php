<?php
namespace wstmart\app\controller;
use wstmart\common\exception\AppException as AE;
use wstmart\common\model\Goods as G;
use wstmart\common\model\GoodsCats as GC;
use wstmart\common\model\Attributes as AT;
use wstmart\app\service\Goods as SGoods;
use wstmart\common\service\Pintuan as SPintuan;
use wstmart\common\helper\Redis;
use wstmart\app\service\Users as ASUsers;
use wstmart\common\service\Goods as SG;

/**
 * 商品控制器
 */
class Goods extends Base {
    protected $beforeActionList = [
          'checkAuth' => ['only'=>'browseisreward,browsereward,historyquery,favoritelist,addfavorite,
          cancelfavorite,sharepintuan']
    ];
    protected $openAction = [
        'browseisreward',
        'getgoodscatsdata',
        'sharebrowsereward',
        'activitygoodslist',
        'info',
        'share',
        'addshare',
        'addfavorite',
        'cancelfavorite',
        'recommendlist',
        'infopageappraises',
        'favoritelist',
        'getgoodstagslist',
        'browsereward',
    ];

    /**
     * 优选列表
     */
    public function recommendList()
    {
        $offset = getInput('post.offset', 1);
        $pageSize = getInput('post.pageSize', 5);
        $key = getInput('post.key');
        if (!checkInt($offset, false) || !checkInt($pageSize, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        if ($offset > 1 && empty($key)) {
            throw AE::factory(AE::COM_REQUEST_ERR);
        }
        if ($offset == 1) {
            $key = get5MinuteDateKey();
        }

        $goodsService = new SGoods();
        $userQuery = ASUsers::getUserByCache();
        $rs = $goodsService->recommendList($offset, $pageSize, $key, $userQuery['userId'], $userQuery['commonParams']);
        return $this->shopJson($rs);
    }

    /**
     * 商品详情
     */
    public function info()
    {
        $goodsId = getInput('post.goodsId');
        if (!is_numeric($goodsId)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $goodsService = new SGoods();
        $rs = $goodsService->info($goodsId);
        return $this->shopJson($rs);
    }

    /**
     * 商品详情页评论
     */
    public function infoPageAppraises()
    {
        $goodsId = getInput('post.goodsId');
        if (!is_numeric($goodsId)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }

        $goodsService = new SGoods();
        $rs = $goodsService->infoPageAppraises($goodsId);
        return $this->shopJson($rs);
    }

    /**
     * 添加商品收藏
     */
    public function addFavorite()
    {
        $goodsId = getInput('post.goodsId');
        if (!is_numeric($goodsId)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $userId = ASUsers::getUserByCache()['userId'];
        $goodsService = new SGoods();
        $rs = $goodsService->addFavorite($goodsId, $userId);
        return $this->shopJson($rs);
    }

    /**
     * 取消商品收藏
     */
    public function cancelFavorite()
    {
        $goodsId = getInput('post.goodsId');
        if (!is_numeric($goodsId)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $goodsService = new SGoods();
        $rs = $goodsService->cancelFavorite($goodsId);
        return $this->shopJson($rs);
    }

    /**
     * 商品收藏列表
     */
    public function favoriteList()
    {
        $userId = ASUsers::getUserByCache()['userId'];
        $offset = getInput('post.offset', 1);
        $pageSize = getInput('post.pageSize', 5);
        if (!checkInt($offset, false) || !checkInt($pageSize, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }

        $goodsModel = new G();
        $rs = $goodsModel->favoriteList($userId, $offset, $pageSize);
        return $this->shopJson($rs);
    }

    /**
     * 活动商品列表
     */
    public function activityGoodsList()
    {
        $activity = getInput('post.activity');
        $offset = getInput('post.offset', 1);
        $pageSize = getInput('post.pageSize', 5);
        if (!checkInt($offset, false) || !checkInt($pageSize, false) || empty($activity)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }

        $userQuery = ASUsers::getUserByCache();
        $rs = (new SG())->activityGoodsList($activity, $offset, $pageSize, $userQuery['userId'], $userQuery['commonParams']);
        return $this->shopJson($rs);
    }

    /**
     * 分享商品
     */
    public function share()
    {
        $goodsId = getInput('post.goodsId');
        if (!is_numeric($goodsId)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $userId = ASUsers::getUserByCache()['userId'];
        $goodsService = new SGoods();
        $rs = $goodsService->share($goodsId, $userId);
        return $this->shopJson($rs);
    }

    public function addShare()
    {
        $goodsId = getInput('post.goodsId');
        if (!checkInt($goodsId, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $goodsService = new SGoods();
        $rs = $goodsService->addShare($goodsId);
        return $this->shopJson($rs);
    }

    public function browseIsReward()
    {
        $goodsId = getInput('post.goodsId');
        if (!checkInt($goodsId, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $userId = ASUsers::getUserByCache()['userId'];
        $goodsService = new SGoods();
        $rs = $goodsService->browseIsReward($goodsId, $userId);
        return $this->shopJson($rs);
    }

    public function browseReward()
    {
        $goodsId = getInput('post.goodsId');
        $score = getInput('post.score');
        if (!checkInt($goodsId, false) && !checkInt($score, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        if ($score > 50 || $score < 10) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $userId = ASUsers::getUserByCache()['userId'];
        $goodsService = new SGoods();
        $rs = $goodsService->browseReward($goodsId, $score, $userId);
        return $this->shopJson($rs);
    }

    public function shareBrowseReward()
    {
        $inviteCode = getInput('post.inviteCode');
        $goodsId = getInput('post.goodsId');
        $time = getInput('post.time');
        if (empty($inviteCode) || !checkInt($goodsId, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $userId = ASUsers::getUserByCache()['userId'];
        $goodsService = new SGoods();
        $rs = $goodsService->shareBrowseReward($inviteCode, $userId, $goodsId, $time);
        return $this->shopJson($rs);
    }

    // 获取猜你喜欢
    public function getGuess(){
        $catId = (int)input('catId');
        $goodsIds = explode(',',input('goodsIds'));
        if(!empty($goodsIds))$goodsIds = array_unique($goodsIds);
        // 猜你喜欢6件商品
        $like = model('Tags')->getGuessLike($catId,6,$goodsIds);
        foreach($like as $k=>$v){
            // 删除无用字段
            unset($like[$k]['shopName']);
            unset($like[$k]['shopId']);
            unset($like[$k]['goodsSn']);
            unset($like[$k]['goodsStock']);
            unset($like[$k]['marketPrice']);
            unset($like[$k]['isSpec']);
            unset($like[$k]['appraiseNum']);
            unset($like[$k]['visitNum']);
            // 替换商品图片
            $like[$k]['goodsImg'] = WSTImg($v['goodsImg'],3);
        }
        $rs = [
            'domain'=>$this->domain(),
            'goods'=>$like
        ];
        return json_encode(WSTReturn('ok',1,$rs));
    }
    // 获取商品主图及商品名称
    public function preloadGoods(){
        $m = model('app/goods');
        $rs = $m->preloadGoods();
        $rs['domain'] = $this->domain();
        return json_encode(WSTReturn('success',1,$rs));
    }
    /**
    * 商品咨询
    */
    public function getGoodsConsult(){
        $rs = model('GoodsConsult')->firstQuery(input('goodsId/d'));
        return json_encode(WSTReturn('ok',1,$rs));
    }
	/**
	 * 商品主页
	 */
	public function index(){
		$m = model('goods');
        $goods = $m->getBySale(input('goodsId/d'));
        // 找不到商品记录
        if(empty($goods))return json_encode(WSTReturn('未找到商品记录',-1));
        // 删除无用字段
        WSTUnset($goods,'goodsSn,productNo,isSale,isBest,isHot,isNew,isRecom,goodsCatIdPath,goodsCatId,shopCatId1,shopCatId2,brandId,goodsStatus,saleTime,goodsSeoKeywords,illegalRemarks,dataFlag,createTime,read');
        $goods['domain'] = $this->domain();
        if($goods['isFreeShipping'] == 1){
            $goods['isFreeShipping'] = '免运费';
        }elseif ($goods['isFreeShipping'] == 0) {
            $goods['isFreeShipping'] = sprintf("%.2f",$goods['shop']['freight']);
        }
        return json_encode(WSTReturn('请求成功',1,$goods));
	}
    // 获取商品详情
    public function goodsDetail(){
        $detail = model('goods')->getGoodsDetail((int)input('goodsId'));
        if(empty($detail))die('未找到该商品详情');
        $detail['goodsDesc'] = htmlspecialchars_decode($detail['goodsDesc']);
        $this->assign('goodsDesc',$detail);
        return $this->fetch('goods_desc');
    }

    /**
     * 获取商品列表
     */
    public function pageQuery(){
    	$m = model('goods');
    	$gc = new GoodsCats();
    	$catId = (int)input('catId');
    	if($catId>0){
    		$goodsCatIds = $gc->getParentIs($catId);
    	}else{
    		$goodsCatIds = [];
    	}

         //处理已选属性
        $vs = input('vs');
        $vs = ($vs!='')?explode(',',$vs):[];
        $at = new AT();
        $goodsFilter = $at->listQueryByFilter((int)input('catId/d'));
        $ngoodsFilter = [];
        if(!empty($vs)){
            // 存在筛选条件,取出符合该条件的商品id,根据商品id获取可选属性进行拼凑
            $goodsId = model('goods')->filterByAttributes();

            $attrs = model('Attributes')->getAttribute($goodsId);
            // 去除已选择属性
            foreach ($attrs as $key =>$v){
                if(!in_array($v['attrId'],$vs)){$ngoodsFilter[] = $v;}
            }
        }else{
            // 当前无筛选条件,取出分类下所有属性
            foreach ($goodsFilter as $key =>$v){
                if(!in_array($v['attrId'],$vs))$ngoodsFilter[] = $v;
            }
        }

    	$rs['goodsPage'] = $m->pageQuery($goodsCatIds);

        foreach ($ngoodsFilter as $k => $val) {
           $result = array_values(array_unique($ngoodsFilter[$k]['attrVal']));

           $ngoodsFilter[$k]['attrVal'] = $result;
        }
        $rs['goodsFilter'] = $ngoodsFilter;
        // `券`、`满送`标签  
        hook('afterQueryGoods',['page'=>&$rs['goodsPage'],'isApp'=>true]);
    	foreach ($rs['goodsPage']['data'] as $key =>$v){
    		$rs['goodsPage']['data'][$key]['goodsImg'] = WSTImg($v['goodsImg'],2);
            $rs['goodsPage']['data'][$key]['praiseRate'] = ($v['totalScore']>0)?(sprintf("%.2f",$v['totalScore']/($v['totalUsers']*15))*100).'%':'100%';
    	}
        $rs['domain'] = $this->domain();
    	return json_encode(WSTReturn('数据请求成功',1,$rs));
    }
    /**
    * 商品列表热卖推荐
    */
    public function getCatRecom(){
        $catId = (int)input('catId');
        $rs = model('Tags')->listGoods('recom',$catId,8);
        if(!empty($rs)){
            $_rs = [];
            foreach($rs as $k=>$v){
                $_rs[$k]['goodsId'] = $v['goodsId'];
                $_rs[$k]['goodsName'] = $v['goodsName'];
                $_rs[$k]['shopPrice'] = $v['shopPrice'];
                $_rs[$k]['goodsImg'] = $v['goodsImg'];
            }
            return json_encode(WSTReturn('数据请求成功',1,$_rs));
        }else{
            return json_encode(WSTReturn('暂无热卖推荐',-1));
        }

    }
    /**
    * 获取浏览历史
    */
    public function historyQuery(){
        $data['list'] = model('goods')->historyQuery();
        if(!empty($data['list'])){
	        foreach($data['list'] as $k=>$v){
	            $data['list'][$k]['goodsImg'] = WSTImg($v['goodsImg'],3);
	        }
        }
        // 域名
        $data['domain'] = $this->domain();
        return json_encode(WSTReturn('数据请求成功',1,$data));
    }

    /************************************新添加**************************************/

    /**
     * 获取商品分类下的商品
     */
    public function getGoodsCatsData(){
        $catId = getInput('catId');
        $offset = getInput('post.offset', 1);
        $pageSize = getInput('post.pageSize', 5);
        $keyTime = getInput('post.key');
        $userQuery = ASUsers::getUserByCache();
        if (empty($catId)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        if (!checkInt($offset, false) || !checkInt($pageSize, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        if ($offset > 1 && empty($keyTime)) {
            throw AE::factory(AE::COM_REQUEST_ERR);
        }
        if ($offset == 1) {
            $keyTime = get5MinuteDateKey();
        }
        $sg = new SG();
        $rs = $sg->getGoodsCatsData($catId, $offset, $pageSize, $keyTime, $userQuery['commonParams']);
        return $this->shopJson($rs);
    }

    public function getGoodsTagsList()
    {
        $inviteCode = getInput('inviteCode', '');
        $offset = getInput('offset', 1);
        $pageSize = getInput('pageSize', 5);
        $userQuery = ASUsers::getUserByCache();
        $sg = new SG();
        $rs = $sg->getGoodsTagsList($inviteCode, $userQuery['commonParams'], $offset, $pageSize);
        return $this->shopJson($rs);
    }
}
