<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/10/20
 * Time: 9:42
 */
namespace wstmart\app\controller;

use \wstmart\app\service\Users as ASUsers;
use \wstmart\common\service\TaskWelfare as CSTaskWelfare;
use wstmart\common\exception\AppException as AE;

class Task extends Base
{
    protected $beforeActionList = [
        'checkAuth'=>[
            'except'=>'signrule,bindfanscarousel'
        ],
    ];
    protected $openAction = [
        'browsegoodsisfinish',
        'bindfanscarousel',
        'sharelist',
        'fansrank',
        'signrule',
    ];

    public function bindFansCarousel()
    {
        $rs = (new CSTaskWelfare())->bindFansCarousel();
        return $this->shopJson($rs);
    }

    public function browseGoodsIsFinish()
    {
        $userId = ASUsers::getUserByCache()['userId'];
        $rs = [
            'isFinish' => (new CSTaskWelfare())->browseGoodsIsFinish($userId)
        ];
        return $this->shopJson($rs);
    }

    public function fansRank()
    {
        $userId = ASUsers::getUserByCache()['userId'];
        $rs = (new CSTaskWelfare())->fansRank($userId);
        return $this->shopJson($rs);
    }

    public function signRule()
    {
        $rule = <<<RULE
1:签到可以获得U币奖励和红包奖励<br/>2:连续签到3天可领取3元无门槛红包<br/>3:连续签到7天可以获得一次抽奖机会，翻牌即可获得各类神秘奖品<br/>4:一个签到周期为7天，每完成7天签到，自动开启新的周期<br/>5:如果漏签则开启新的周期<br/>6:获得的红包需在有效期内使用，过期则红包即刻失效，请注意红包有效期
RULE;
        $rs = [
            'rule' => $rule,
        ];
        return $this->shopJson($rs);
    }

    public function shareList()
    {
        $offset = getInput('post.offset');
        $pageSize = getInput('post.pageSize');

        if (!checkInt($offset, false) || !checkInt($pageSize, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $userId = ASUsers::getUserByCache()['userId'];
        $rs = (new CSTaskWelfare())->shareList($userId, $offset, $pageSize);
        return $this->shopJson($rs);
    }
}