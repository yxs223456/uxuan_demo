<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/4
 * Time: 17:52
 */
namespace wstmart\common\service;

use wstmart\common\model\UserAddress as M;
use wstmart\app\service\Users as ASUsers;
use wstmart\common\exception\AppException as AE;
use wstmart\common\service\TaskWelfare as CSTW;
use think\Db;

class UserAddress
{
    public function save($addressId, $name, $phone, $province, $city, $county, $address, $isDefault, $areaIdPath, $countyId)
    {
        $m = new M();
        $userId = ASUsers::getUserByCache()['userId'];
        if (!$addressId) {
            $count = $m->where(['userId'=>$userId, 'dataFlag'=>1])->count();
            if ($count >= 20) {
                throw AE::factory(AE::USER_ADDRESS_NUM_TOP);
            }
            $addressId = $m->add($userId, $name, $phone, $province, $city, $county, $address, $isDefault, $areaIdPath, $countyId);
        } else {
            $m->modify($addressId, $userId, $name, $phone, $province, $city, $county, $address, $isDefault, $areaIdPath, $countyId);
        }
        if ($isDefault) {
            DB::name('user_address')
                ->where("addressId != $addressId and userId = " . $userId)
                ->setField('isDefault',0);
        }
        (new CSTW())->completeInformation($userId);
        return [
            'addressId' => $addressId
        ];
    }
}