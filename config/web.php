<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/9
 * Time: 18:43
 */
return [
    'order_timeout' => 1800,//订单超时时间，半小时
    'order_timeout_notify' => 900,//订单超时倒计时提醒时间，提前15分钟提醒
    'order_receive_time' => 1296000,//自动确认收货，15天
    'order_refund_time' => 604800,//售后自动退款时间，7天
    'wx_untie_time' => 180,//微信解綁时间，7天
    'refund_time' => 604800,//收获时间超过7天不能申请退款
    'noviceCouponExpireDate'=>2592000, //新手红包过期时间
    'adsListExpireDate'=>28800, //广告列表有效时间8小时
    'domain' => getAppEnvironment() == 'production' ? 'http://uxuan.local.com' : 'http://uxuan.local.com',
    'image_domain' => getAppEnvironment() == 'production' ? 'http://uxuan.local.com' : 'http://uxuan.local.com',
    'order_status' => [
        'timeout' => -6,//超时
        'pintuanFail' => -5,//拼团失败
        'waitPintuan' => -4,//待成团
        'refuseReceive' => -3,//用户拒收
        'waitPay' => -2,//待付款
        'cancel' => -1,//取消订单
        'waitDeliver' => 0,//待发货
        'delivering' => 1,//配送中
        'waitAppraise' => 2,//待评价
        'appraiseAlready' => 3,//已评价
    ],
    'refund_status' => [
        'refundRefuse' => -1, //商家不同意
        'refundApply' => 0, //等待商家处理
        'refundSuccess' => 1, //商家同意退款
        'userRevoke' => 3, //用户撤销
        'returnGoodsApplySuccess' => 4, //商家同意退货退款
    ],
    'after_sale_status' => [
        'noApply' => 0,//未申请售后
        'refundApply' => 1,//退款申请中,数据库中用afterSaleStatus字段表示
        'refundSuccess' => 2,//退款成功,数据库中用afterSaleStatus字段表示
        'refundRefuse' => 3,//退款拒绝,数据库中用afterSaleStatus字段表示
        'returnGoodsApplySuccess' => 4,//客服同意退货退款
        'userRevoke' => 5,//用户撤销
    ],
    'day_login_award' => 50,//每日登录奖励积分
    'continuous_login_award' => [//连续登录奖励积分数
        [
            'days' => 5,
            'score' => 20,
        ],
        [
            'days' => 20,
            'score' => 50,
        ]
    ],
    'h5_url' => 'http://uxuan.local.com',
    'static_url' => 'http://uxuan.local.com',
    'app_name' => '每日优选',
    'wx_app_notify_url' => '/app/Payments/delWeChatPayCallBackUrl',
    'wx_app_refund_url' => '/app/Payments/delWeChatRefundCallBackUrl',
    'wx_pay_tag' => '每日优选',
    'targetChannel' => [
        0=>'安卓&ios',
        1=>'安卓',
        2=>'ios'
    ],
    'guideType' => [
        0=>'引导图',
        1=>'引导视频',
        2=>'开屏图'
    ],
    'useCondition' => [
        0=>'无门槛',
        1=>'满减',
        2=>'折扣'
    ],
    'accessModuleType'=>['商品详情', '分类列表', '标签列表', '红包中心', '红包集市'],
    'newsType'=>[
        'favourable'=>1,
        'system'=>2,
    ],
    'orderSrcType'=>['mp'=>5,'app'=>3,'h5'=>2],
    'auditChannel'=>[
        'tencent'=>'腾讯应用宝',
        'AppStore'=>'ios',
        'miniProgram'=>'小程序',
        'qh360'=>'360手机助手',
        'baidu91'=>'百度手机助手+91+安卓市场',
        'xiaomi'=>'小米应用商店',
        'wandoujia'=>'豌豆荚',
        'anzhi'=>'安智市场',
        'huawei'=>'华为市场',
        'meizu'=>'魅族市场',
        'oppo'=>'OPPE',
        'vivo'=>'VIVO',
        'androidDev'=>'androidDev'
    ],
    'temp_appraises_open' => false,
    'city_file_path' => getAppEnvironment() == 'production' ? '/upload/sysconfigs/2018-09/5b8cce9813bac.json' :
        '/upload/sysconfigs/2018-09/5b8cdc42cd75d.json',
    'logistics' => getAppEnvironment() == 'production' ? [
        ['company'=>'圆通快递','icon'=>'/upload/sysconfigs/2018-09/5b8ccfa3cafab.png'],
        ['company'=>'韵达快递','icon'=>'/upload/sysconfigs/2018-09/5b8ccfb45dd66.png'],
        ['company'=>'申通快递','icon'=>'/upload/sysconfigs/2018-09/5b8ccfc54002d.png'],
        ['company'=>'顺丰快递','icon'=>'/upload/sysconfigs/2018-09/5b8ccfd210ea0.png'],
        ['company'=>'中通快递','icon'=>'/upload/sysconfigs/2018-09/5b8ccfe1a44fc.png'],
        ['company'=>'宅急送','icon'=>'/upload/sysconfigs/2018-09/5b8ccfedd480d.png'],
        ['company'=>'德邦快递','icon'=>'/upload/sysconfigs/2018-09/5b8cd000460aa.png'],
        ['company'=>'EMS快递','icon'=>'/upload/sysconfigs/2018-09/5b8cd00cb6204.png'],
        ['company'=>'邮政快递','icon'=>'/upload/sysconfigs/2018-09/5b8cd01a3a98c.png'],
        ['company'=>'如风达快递','icon'=>'/upload/sysconfigs/2018-09/5b8cd026eae1c.png'],
        ['company'=>'DHL快递','icon'=>'/upload/sysconfigs/2018-09/5b8cd03377eb2.png'],
        ['company'=>'UPS快递','icon'=>'/upload/sysconfigs/2018-09/5b8cd03e6ef95.png'],
        ['company'=>'更多','icon'=>'/upload/sysconfigs/2018-09/5b8cd03e6ef95.png'],
    ] : [
        ['company'=>'圆通快递','icon'=>'/upload/sysconfigs/2018-09/5b8cdbe76aa9f.png'],
        ['company'=>'韵达快递','icon'=>'/upload/sysconfigs/2018-09/5b8cdc15b0eab.png'],
        ['company'=>'申通快递','icon'=>'/upload/sysconfigs/2018-09/5b8cdc1f00da8.png'],
        ['company'=>'顺丰快递','icon'=>'/upload/sysconfigs/2018-09/5b8cdc2b42b6e.png'],
        ['company'=>'中通快递','icon'=>'/upload/sysconfigs/2018-09/5b8cdc2b42b6e.png'],
        ['company'=>'宅急送','icon'=>'/upload/sysconfigs/2018-09/5b8cdc2b42b6e.png'],
        ['company'=>'德邦快递','icon'=>'/upload/sysconfigs/2018-09/5b8cdc2b42b6e.png'],
        ['company'=>'EMS快递','icon'=>'/upload/sysconfigs/2018-09/5b8cdc2b42b6e.png'],
        ['company'=>'邮政快递','icon'=>'/upload/sysconfigs/2018-09/5b8cdc2b42b6e.png'],
        ['company'=>'如风达快递','icon'=>'/upload/sysconfigs/2018-09/5b8cdc2b42b6e.png'],
        ['company'=>'DHL快递','icon'=>'/upload/sysconfigs/2018-09/5b8cdc2b42b6e.png'],
        ['company'=>'UPS快递','icon'=>'/upload/sysconfigs/2018-09/5b8cdc2b42b6e.png'],
        ['company'=>'更多','icon'=>'/upload/sysconfigs/2018-09/5b8e00f4407b3.png'],
    ],
    'score_type' => [
        'coupon' => '兑换红包',
        'sign' => '每日签到',
        'signLottery' => '签到抽奖',
        'completeInformation' => '完善资料',
        'addFavoriteTag' => '选我喜欢',
        'bindUxuan' => '绑定微信公众号',
        'inviteFans' => '收徒',
        'bindFans' => '拜师',
        'browseGoods' => '爱逛街',
        'favoriteGoods' => '疯狂打CALL',
        'shareBrowse' => '给好友种草',
        'pintaunOriginator' => '我的团长我的团',
        'fansInviteFans' => '徒弟收徒',
        'fansBrowseGoods' => '徒弟逛街',
        'fansFavoriteGoods' => '徒弟打CALL',
        'fansShareBrowse' => '徒弟种草',
        'fansPintaunOriginator' => '徒弟开团',
    ],
    'commission_ratio' => 0.1,































    'sendOrderWaitPay'=>'SendOrderWaitPay',
    'sendPintuanPeopleLess'=>'SendPintuanPeopleLess',
];