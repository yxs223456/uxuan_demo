<?php
namespace wstmart\common\model;
use wstmart\common\exception\AppException;
use wstmart\common\validate\UserAddress as Validate;
use wstmart\common\exception\AppException as AE;
use think\Db;
/**
 * 用户地址
 */
class UserAddress extends Base{
    
    protected $pk = 'addressId';

    public function getAddressById($addressId)
    {
        $address = $this->where('addressId', $addressId)->find();
        if (empty($address)) {
            throw AE::factory(AE::USER_ADDRESS_NOT_EXISTS);
        }
        return $address;
    }

    /**
     * 新增
     */
    public function add($userId, $name, $phone, $province, $city, $county, $address, $isDefault, $areaIdPath, $countyId)
    {
        $addressInfo = [
            'userId' => $userId,
            'userName' => $name,
            'userPhone' => $phone,
            'province' => $province,
            'city' => $city,
            'county' => $county,
            'areaIdPath' => $areaIdPath,
            'areaId' => $countyId,
            'userAddress' => $address,
            'isDefault' => $isDefault,
            'createTime' => date('Y-m-d H:i:s')
        ];
        $this->save($addressInfo);
        return $this->addressId;
    }

    /**
     * 修改地址
     */
    public function modify($addressId, $userId, $name, $phone, $province, $city, $county, $address, $isDefault, $areaIdPath, $countyId)
    {
        $addressModel = $this->getAddressById($addressId);
        if ($addressModel->userId != $userId) {
            throw AE::factory(AE::COM_PARAMS_ERR);
        }
        $addressInfo = [
            'userName' => $name,
            'userPhone' => $phone,
            'province' => $province,
            'city' => $city,
            'county' => $county,
            'areaIdPath' => $areaIdPath,
            'areaId' => $countyId,
            'userAddress' => $address,
            'isDefault' => $isDefault
        ];
        $this->save($addressInfo, ['addressId'=>$addressId]);
    }

    public function getDefault($userId)
    {
        $default = $this
            ->where('userId', $userId)
            ->where('isDefault', 1)
            ->where('dataFlag', 1)
            ->find();
        return $default;
    }

    public function getList($userId)
    {
        $list = $this->where('userId', $userId)
            ->where('dataFlag', 1)
            ->field('addressId,userName,userPhone,province,city,county,userAddress,isDefault,areaIdPath')
            ->order('createTime', 'desc')
            ->select();
        return $list;
    }

    public function getCount($userId)
    {
        $count = $this->where('userId', $userId)
            ->where('dataFlag', 1)
            ->count();
        return $count;
    }

    public function checkAddressIsDefault($userId, $province, $city, $county, $address, $userPhone)
    {
        try {
            $default = $this->getDefault($userId);
            if ($default['province'] == $province && $default['city'] == $city
                && $default['county'] == $county && $default['userAddress'] == $address
                && $default['userPhone'] == $userPhone) {
                return true;
            } else {
                return false;
            }
        } catch (AE $e) {
            if ($e->getCode() != (AE::USER_ADDRESS_DEFAULT_NOT_EXISTS)[0]) {
                throw $e;
            }
            return false;
        }
    }

    /**
     * 设置为默认地址
     */
    public function setDefault($userId, $addressId){
        $this->where([['addressId','<>',$addressId],['userId','=',$userId]])->setField('isDefault',0);
        $this->where("addressId = $addressId and userId=".$userId)->setField('isDefault',1);
        return true;
    }

     /**
      * 获取列表
      */
      public function listQuery($userId){
         $where = ['userId'=>(int)$userId,'dataFlag'=>1];
         $rs = $this->order('isDefault asc, addressId desc')->where($where)->select();
         $areaIds = [];
         $areaMaps = [];
         foreach ($rs as $key => $v){
         	 $tmp = explode('_',$v['areaIdPath']);
         	 foreach ($tmp as $vv){
         		if($vv=='')continue;
         	    if(!in_array($vv,$areaIds))$areaIds[] = $vv;
         	 }
         	 $rs[$key]['areaId2'] = $tmp[1];
         }
         if(!empty($areaIds)){
	         $areas = Db::name('areas')->where([['areaId','in',$areaIds],['isShow','=',1],['dataFlag','=',1]])->field('areaId,areaName')->select();
	         foreach ($areas as $v){
	         	 $areaMaps[$v['areaId']] = $v['areaName'];
	         }
	         foreach ($rs as $key => $v){
	         	$tmp = explode('_',$v['areaIdPath']);
	         	$areaNames = [];
                $isFind = true;
		        foreach ($tmp as $vv){
	         		if($vv=='')continue;
                    if(!isset($areaMaps[$vv])){
                        $isFind = false;
                        continue;
                    }
	         	    $areaNames[] = $areaMaps[$vv];
	            }
                if($isFind){
    	         	$rs[$key]['areaName'] = implode('',$areaNames);
    	         	$rs[$key]['areaName1'] = $areaMaps[$v['areaId2']];
                }
	         }
             $tmp = [];
             for($i=count($rs)-1;$i>=0;$i--){
                if(isset($rs[$i]['areaName']))$tmp[] = $rs[$i];
             }
             $rs = $tmp;
         }
         return $rs;
      }
    /**
    *  获取用户信息
    */
    public function getById($id, $uId=0){
        $userId = ((int)$uId==0)?(int)session('WST_USER.userId'):$uId;
    	$rs = $this->get(['addressId'=>$id,'userId'=>$userId,'dataFlag'=>1]);
        if(empty($rs))return [];
        $areaIds = [];
        $areaMaps = [];
        $tmp = explode('_',$rs['areaIdPath']);
        $rs['areaId2'] = $tmp[1];
        foreach ($tmp as $vv){
         	if($vv=='')continue;
         	if(!in_array($vv,$areaIds))$areaIds[] = $vv;
        }
        if(!empty($areaIds)){
	         $areas = Db::name('areas')->where([['areaId','in',$areaIds],['isShow','=',1],['dataFlag','=',1]])->field('areaId,areaName')->select();
	         foreach ($areas as $v){
	         	 $areaMaps[$v['areaId']] = $v['areaName'];
	         }
	         $tmp = explode('_',$rs['areaIdPath']);
	         $areaNames = [];
		     foreach ($tmp as $vv){
	         	 if($vv=='')continue;
                 if(!isset($areaMaps[$vv]))return [];
	         	 $areaNames[] = $areaMaps[$vv];
	         	 $rs['areaName'] = implode('',$areaNames);
	         }
         }
        return $rs;
    }

    /**
     * 新增
     */
    /*public function add($uId=0){
        $data = input('post.');
        unset($data['addressId']);
        $data['userId'] = ((int)$uId==0)?(int)session('WST_USER.userId'):$uId;
        $data['createTime'] = date('Y-m-d H:i:s');
        if($data['userId']==0)return WSTReturn('新增失败，请先登录');
        // 检测是否存在下级地区
        $hasChild = model('Areas')->hasChild(input('areaId'));
        if($hasChild)return WSTReturn('请选择完整的地区信息',-1);

        $areaIds = model('Areas')->getParentIs((int)input('areaId'));
        if(!empty($areaIds))$data['areaIdPath'] = implode('_',$areaIds)."_";
        $validate = new Validate;
        if (!$validate->scene('add')->check($data)) {
        	return WSTReturn($validate->getError());
        }else{
        	$result = $this->allowField(true)->save($data);
        }
        if(false !== $result){
            //修改默认地址
            if((int)input('post.isDefault')==1){
            	$this->where("addressId != $this->addressId and userId=".$data['userId'])->setField('isDefault',0);
            }
            return WSTReturn("新增成功", 1,['addressId'=>$this->addressId]);
        }else{
            return WSTReturn($this->getError(),-1);
        }
    }*/

    /**
     * 编辑资料
     */
    public function edit($uId=0){
        $userId = ((int)$uId==0)?(int)session('WST_USER.userId'):$uId;
        $id = (int)input('post.addressId');
        $data = input('post.');
        // 检测是否存在下级地区
        $hasChild = model('Areas')->hasChild(input('areaId'));
        if($hasChild)return WSTReturn('请选择完整的地区信息',-1);
        
        $areaIds = model('Areas')->getParentIs((int)input('areaId'));
        if(!empty($areaIds))$data['areaIdPath'] = implode('_',$areaIds)."_";
        $validate = new Validate;
        if (!$validate->scene('edit')->check($data)) {
        	return WSTReturn($validate->getError());
        }else{
        	$result = $this->allowField(true)->save($data,['addressId'=>$id,'userId'=>$userId]);
        }
        //修改默认地址
        if((int)input('post.isDefault')==1)
          $this->where("addressId != $id and userId=".$userId)->setField('isDefault',0);
        if(false !== $result){
            return WSTReturn("编辑成功", 1);
        }else{
            return WSTReturn($this->getError(),-1);
        }
    }
    /**
     * 删除
     */
    public function del($uId=0){
        $userId = ((int)$uId==0)?(int)session('WST_USER.userId'):$uId;
        $id = input('post.id/d');
        $data = [];
        $data['dataFlag'] = -1;
        $result = $this->update($data,['addressId'=>$id,'userId'=>$userId]);
        if(false !== $result){
            return WSTReturn("删除成功", 1);
        }else{
            return WSTReturn($this->getError(),-1);
        }
    }

    /**
    * 设置为默认地址
    */
    /*public function setDefault($uId=0){
        $userId = ((int)$uId==0)?(int)session('WST_USER.userId'):$uId;
        $id = (int)input('post.id');
        $this->where([['addressId','<>',$id],['userId','=',$userId]])->setField('isDefault',0);
        $rs = $this->where("addressId = $id and userId=".$userId)->setField('isDefault',1);
        if(false !== $rs){
            return WSTReturn("设置成功", 1);
        }else{
            return WSTReturn($this->getError(),-1);
        }
    }*/
    /**
     * 获取默认地址
     */
    public function getDefaultAddress($uId=0){
    	$userId = ((int)$uId==0)?(int)session('WST_USER.userId'):$uId;
    	$where = ['userId'=>$userId,'dataFlag'=>1];
        $rs = $this->where($where)->order('isDefault desc,addressId desc')->find();
        if(empty($rs))return [];
        $areaIds = [];
        $areaMaps = [];
        $tmp = explode('_',$rs['areaIdPath']);
        $rs['areaId2'] = $tmp[1];
        foreach ($tmp as $vv){
         	if($vv=='')continue;
         	if(!in_array($vv,$areaIds))$areaIds[] = $vv;
        }
        if(!empty($areaIds)){
	         $areas = Db::name('areas')->where([['areaId','in',$areaIds],['isShow','=',1],['dataFlag','=',1]])->field('areaId,areaName')->select();
	         foreach ($areas as $v){
	         	 $areaMaps[$v['areaId']] = $v['areaName'];
	         }
	         $tmp = explode('_',$rs['areaIdPath']);
	         $areaNames = [];
		     foreach ($tmp as $vv){
	         	 if($vv=='')continue;
                 if(!isset($areaMaps[$vv]))return [];
	         	 $areaNames[] = $areaMaps[$vv];
	         	 $rs['areaName'] = implode('',$areaNames);
	         }
         }
         return $rs;
    }

    /**
     * 获取地址信息(接口)
     */
    public function getAddressInfo($where)
    {
        if (empty($where)) {
            throw AE::factory(AE::COM_PARAMS_EMPTY);
        }
        $address = DB::name('user_address')->where($where)->find();
        if (!$address) {
            throw AE::factory(AE::USER_ADDRESS_NOT_EXISTS);
        }
        $tmp_areaId = explode('_', $address['areaIdPath']);
        $areaId2 = $tmp_areaId[1];
        $addressName = $address['province'].' '.$address['city'].' '.$address['county'];
        $address['addressName'] = $addressName;
        $address['areaId2'] = $areaId2;
        return $address;

    }
}
