<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/9/14
 * Time: 14:33
 */

namespace wstmart\app\controller;

use wstmart\app\service\Users as ASUsers;
use wstmart\common\service\Coupons as SC;
use wstmart\common\exception\AppException as AE;

class Coupons extends Base
{
    protected $beforeActionList = [
        'checkAuth' => ['only'=>'novicegiftbaglist,receivenovicegiftbag,coupongoodslist,exchangelist']
    ];
    protected $openAction = [
        'novicegiftbaglist',
        'receivenovicegiftbag',
        'coupongoodslist',
        'exchangelist',
    ];

    /*
     * 新手礼包列表
     */
    public function noviceGiftBagList()
    {
        $userId = ASUsers::getUserByCache()['userId'];
        $sc = new SC();
        $data = $sc->noviceGiftBag($userId);
        return $this->shopJson($data);
    }

    /*
     * 一键领取新手礼包
     */
    public function receiveNoviceGiftBag()
    {
        $couponIds = getInput('couponIds');
        if (empty($couponIds)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $userId = ASUsers::getUserByCache()['userId'];
        $sc = new SC();
        $data = $sc->receiveNoviceGiftBag($couponIds, $userId);
        return $this->shopJson($data);
    }

    /*
     * 红包制定商品列表
     */
    public function couponGoodsList()
    {
        $couponId = getInput('couponId');
        $offset = getInput('offset', 1);
        $pagesize = getInput('pagesize');
        $pageSize = getInput('pageSize');
        $page = $pagesize ?? $pageSize;
        if (empty($page)) {
            $page = 5;
        }
        if (checkInt($couponId,false)==false) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $userId = ASUsers::getUserByCache()['userId'];
        $userQuery = ASusers::getUserByCache();
        $sc = new SC();
        $data = $sc->couponGoodsList($couponId, $userId, $userQuery['commonParams'], $offset, $page);
        return $this->shopJson($data);
    }

    public function exchangeList()
    {
        $offset = getInput('post.offset');
        $pageSize = getInput('post.pageSize');
        if (!checkInt($offset, false) || !checkInt($pageSize, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $userId = ASUsers::getUserByCache()['userId'];

        $rs = (new SC())->exchangeList($userId, $offset, $pageSize);
        return $this->shopJson($rs);
    }
}