<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/8/11
 * Time: 11:04
 */

namespace wstmart\admin\validate;

use think\Validate;

class Guides extends Validate
{
    protected $rule = [
        'path' => 'require|min:10',
        'link' => 'require|min:10',
        'proportion' => 'number|in:0,1',
        'accessType' => 'number|in:0,1',
        'targetChannel' => 'number|in:0,1,2',
        'status' => 'number|in:0,1',
    ];
    protected $message = [
        'catName.require' => '请输入文件名称',
        'catName.max' => '规格名称不能超过10个字符',
        'link.require' => '请输入访问地址',
        'proportion.number' => '请选择图片比例',
        'proportion.in' => '请选择图片比例',
        'targetChannel.number' => '请选择目标渠道',
        'targetChannel.in' => '请选择目标渠道',
        'accessType.number' => '请选择访问类型',
        'accessType.in' => '请选择访问类型',
        'status.number' => '请选择是否上线',
        'status.in' => '请选择是否上线',
    ];
    protected $scene = [
        'add'=>['path','link','proportion','accessType','targetChannel','status'],
        'edit'=>['path','link','proportion','accessType','targetChannel','status']
    ];
}