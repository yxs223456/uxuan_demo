<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/8/23
 * Time: 16:33
 */

namespace wstmart\admin\validate;

use think\Validate;

class GoodsTempAppraises extends Validate
{
    protected $rule = [
        'goodsId' => 'number',
        'nickname' => 'require|min:2',
        'userPhoto' => 'require',
        'goodsScore' => 'number|between:1,5',
        'timeScore' => 'number|between:1,5',
    ];
    protected $message = [
        'goodsId.number' => '请输入商品id',
        'nickname.require' => '请输入昵称',
        'userPhoto.require' => '头像不能为空',
        'goodsScore.number' => '请输入1-5的数字',
        'timeScore.number' => '请输入1-5的数字',
    ];
    protected $scene = [
        'add'=>['goodsId','nickname','userPhoto','goodsScore','timeScore'],
        'edit'=>['goodsId','nickname','userPhoto','goodsScore','timeScore'],
    ];
}