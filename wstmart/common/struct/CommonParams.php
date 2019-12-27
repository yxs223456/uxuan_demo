<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/10
 * Time: 14:08
 */
namespace wstmart\common\struct;

class CommonParams extends Base
{
    public $version;//客户端版本号
    public $channel;//渠道
    public $brand;//手机品牌
    public $model;//手机型号
    public $source;//客户端
    public $apiVersion;//接口版本号
    public $system;//操作系统
    public $xmRegid;//小米推送唯一设备id

    protected $_types = [
        'version' => 'string',
        'channel' => 'string',
        'brand' => 'string',
        'model' => 'string',
        'source' => 'string',
        'apiVersion' => 'string',
        'system' => 'string',
        'xmRegid' => 'string',
    ];
}