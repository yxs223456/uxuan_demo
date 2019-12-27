<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/7/2
 * Time: 18:09
 */
return [

    //任务类型
    "missionType" => [

        "setTimeout" => [
            "desc" => "延迟任务",
            "value" => 1
        ],

        "async" => [
            "desc" => "异步消息",
            "value" => 2
        ],

    ],

    //任务执行状态
    "missionStatus" => [

        "waiting" => [
            "desc" => "未执行",
            "value" => 0
        ],

        "end" => [
            "desc" => "已完成",
            "value" => 1
        ]

    ],

    //商品活动类型
    "goodsActivityType" => [

        "fanli" => [
            "desc" => "返现",
            "value" => 6
        ],

    ],

    //优选用户来源
    "userSource" => [

        "mp" => [
            "desc" => "小程序",
            "value" => 1
        ],

        "app" => [
            "desc" => "app客户端",
            "value" => 2
        ],

        "fanli" => [
            "desc" => "返利",
            "value" => 3
        ],
    ],

    //客户端类型
    "clientType" => [

        "mp" => [
            "desc" => "小程序",
            "value" => "mp"
        ],

        "app" => [
            "desc" => "app客户端",
            "value" => "app"
        ],

        "wx" => [
            "desc" => "公众号",
            "value" => "wx"
        ],

        "h5" => [
            "desc" => "h5",
            "value" => "h5"
        ],
    ],

    'goodsBrowseUv_',//商品流量uv
    'complete_information_',//完善个人资料并添加收货地址
    'score_carousel' => 'score_carousel',//积分轮播数据
    'coupon_carousel' => 'coupon_carousel',//优惠券轮播数据
    'CatsGoodsList'=>'catsListName',    //分类商品缓存名字
    'CatsGoodsAuditSwitchList'=>'catsAuditSwitchListName',    //分类商品缓存名字
    'catSort'=>'catSort',    //默认商品排序
    'catSortUser'=>'catSortUser',    //用户商品排序
    'freight' => 0,            //运费
    'noGoods'=>'商品没有了,请选择其他商品',
    'APPIP'=>'101.200.154.11',
    'adsPOsitionTypeList'=>'adsPOsitionTypeList', //广告位缓存名称
    'dailyLimitNum'=>'dailyLimitNum',
];