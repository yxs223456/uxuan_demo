<?php
namespace wstmart\app\controller;
use wstmart\common\model\UserAddress as M;
use wstmart\common\exception\AppException as AE;
use wstmart\common\service\UserAddress as addressService;
use wstmart\app\service\Users as ASUsers;
/**
 * 用户地址控制器
 */
class UserAddress extends Base{
    protected $openAction = [
        'save',
        'delete',
        'getdefault',
        'list',
        'setdefault',
        'getinfo'
    ];

	// 前置方法执行列表
    protected $beforeActionList = [
        'checkAuth'
    ];
    /**
     * 新增/编辑地址
     */
    public function save(){
        $addressId = getInput('post.addressId');
        $name = getInput('post.userName');
        $phone = getInput('post.userPhone');
        $province = getInput('post.province');
        $city = getInput('post.city');
        $county = getInput('post.county');
        $provinceId = getInput('post.provinceId');
        $cityId = getInput('post.cityId');
        $countyId = getInput('post.countyId');
        $address= getInput('post.userAddress');
        $isDefault = getInput('post.isDefault');
        if (!empty($addressId) && !checkInt($addressId, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        } elseif (empty($name)) {
            throw AE::factory(AE::USER_ADDRESS_NAME_EMPTY);
        } elseif (!WSTIsPhone($phone)) {
            throw AE::factory(AE::USER_ADDRESS_PHONE_ERR);
        } elseif (empty($province)) {
            throw AE::factory(AE::USER_ADDRESS_PROVINCE_EMPTY);
        } elseif (empty($city)) {
            throw AE::factory(AE::USER_ADDRESS_CITY_EMPTY);
        } elseif (empty($county)) {
            throw AE::factory(AE::USER_ADDRESS_COUNTY_EMPTY);
        } elseif (empty($address)) {
            throw AE::factory(AE::USER_ADDRESS_ADDRESS_EMPTY);
        } elseif (!checkIsIn($isDefault, true, '1', '0')) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        } elseif (!checkInt($provinceId, false) || !checkInt($cityId, false) || !checkInt($countyId, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $areaIdPath = $countyId. '_' . $cityId . '_' . $countyId . '_';
        $addressService = new addressService();
        $rs = $addressService->save($addressId, $name, $phone, $province, $city, $county, $address, $isDefault, $areaIdPath, $countyId);
        return $this->shopJson($rs);
    }

    /**
     * 获取默认地址
     */
    public function getDefault()
    {
        $m = new M();
        $default = $m->getDefault(ASUsers::getUserByCache()['userId']);
        if (empty($default)) {
            throw AE::factory(AE::USER_ADDRESS_DEFAULT_NOT_EXISTS);
        }
        $data = [
            'addressId' => $default['addressId'],
            'userName' => $default['userName'],
            'userPhone' => $default['userPhone'],
            'province' => $default['province'],
            'city' => $default['city'],
            'county' => $default['county'],
            'userAddress' => $default['userAddress'],
        ];
        list($data['provinceId'], $data['cityId'], $data['countyId']) = explode('_', $default['areaIdPath']);
        return $this->shopJson($data);
    }

    /**
     * 获取地址列表
     */
    public function list()
    {
        $m = new M();
        $list = $m->getList(ASUsers::getUserByCache()['userId']);
        foreach ($list as &$item) {
            list($item['provinceId'], $item['cityId'], $item['countyId']) = explode('_', $item['areaIdPath']);
            unset($item['areaIdPath']);
        }
        unset($item);
        return $this->shopJson($list);
    }

    /**
     * 设置为默认地址
     */
    public function setDefault(){
        $addressId = getInput('post.addressId');
        if (!checkInt($addressId, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }

        $m = new M();
        $userId = ASUsers::getUserByCache()['userId'];
        $rs = $m->setDefault($userId, $addressId);
        return $this->shopJson($rs);
    }

    public function getInfo()
    {
        $addressId = getInput('post.addressId');
        if (!checkInt($addressId, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }

        $m = new M();
        $address = $m->getAddressById($addressId);
        if ($address->userId != ASUsers::getUserByCache()['userId']) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $rs = [
            'addressId' => $address->addressId,
            'userName' => $address->userName,
            'userPhone' => $address->userPhone,
            'province' => $address->province,
            'city' => $address->city,
            'county' => $address->county,
            'userAddress' => $address->userAddress,
            'isDefault' => $address->isDefault,
        ];
        list($rs['provinceId'], $rs['cityId'], $rs['countyId']) = explode('_', $address['areaIdPath']);
        return $this->shopJson($rs);
    }

    /**
     * 删除地址
     */
    public function delete()
    {
        $addressId = getInput('post.addressId');
        if (!checkInt($addressId, false)) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }

        $m = new M();
        $address = $m->getAddressById($addressId);
        if ($address->userId != ASUsers::getUserByCache()['userId']) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $address->dataFlag = 0;
        $address->isDefault = 0;
        $address->save();
        return $this->shopJson(true);
    }

	/**
	 * 地址管理
	 */
	public function index(){
		$m = new M();
		$userId = model('app/index')->getUserId();
		$addressList = $m->listQuery($userId);
		//获取省级地区信息
		$area = model('app/Areas')->listQuery(0);
		$data = [];
		// 地区信息
		$data['area'] = $area;
		// 已保存的用户地址列表
		$data['list'] = $addressList;

		// type为1时,代表由结算页面跳转过来,设置默认地址按钮,变为选择收货地址
		// type为0时,代表用户直接进入本页面、可直接设置默认地址
		$data['type'] = (int)input('type');

		// 结算页面所选中的地址Id,用于进入用户地址页面时,设置哪个地址为选中
		$data['addressId'] = (int)input('addressId');

		return json_encode(WSTReturn('请求成功', 1, $data));
	}
	/**
	 * 获取地址信息
	 */
	public function getById(){
		$m = new M();
		$userId = model('app/index')->getUserId();
		$rs = $m->getById(input('post.addressId/d'), $userId);
		// 查询到为空,即为新增收货地址,返回省级地址
		$areaM = model('app/Areas');
		if(empty($rs)){
			$rs = $areaM->listQuery(0);
		}else{
			// 获取地区数据
			$rs['area1'] = $areaM->listQuery(0);

			// $rs['area2'] = $areaM->listQuery($rs['areaId2']);
		}
		return json_encode(WSTReturn('请求成功',1,$rs));

	}


	/**
     * 新增/编辑地址
     */
    public function edits(){
        $m = new M();
        $userId = (int)model('app/index')->getUserId();
        if(input('post.addressId/d')){
        	$rs = $m->edit($userId);
        }else{
        	$rs = $m->add($userId);
        } 
        return json_encode($rs);
    }
    /**
     * 删除地址
     */
    public function del(){
    	$m = new M();
    	$userId = (int)model('index')->getUserId();
    	return json_encode($m->del($userId));
    }
}
