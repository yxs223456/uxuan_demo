<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/6/21
 * Time: 17:59
 */
return [
    'h5'=>[
        'appid'=>'wx7bf42b3018c65d73',
        'appsecret'=>'538a762c26b893c4c22e11752689743a',
        'mchkey'=>'3Q3DXErcU3yyfR29oVt8ATVJyvliRiV4',
        'mch_id'=>'1509959201',
        'trade_type'=>'MWEB',
        'sslcert_path'=>'extend/wxpay/cert4/',
        'sslkey_path'=>'extend/wxpay/cert4/',
    ],
    'app'=>[
        'appid'=>'wx57436ba4503ec088',
        'appsecret'=>'b012b6db8835afbedb1f38d909005219',
        'mchkey'=>'3Q3DXErcU3yyfR29oVt8ATVJyvliRiV4',
        'mch_id'=>'1509959201',
        'trade_type'=>'APP',
        'sslcert_path'=>'extend/wxpay/cert4/',
        'sslkey_path'=>'extend/wxpay/cert4/',

    ],
    'miniPrograms'=>[
        'appid'=>'wxf629fdbdaa441335',
        'appsecret'=>'d71fea6094e28ff456c6454c8e2a609d',
        'mchkey'=>'3Q3DXErcU3yyfR29oVt8ATVJyvliRiV4',
        'mch_id'=>'1511601321',
        'trade_type'=>'JSAPI',
        'sslcert_path'=>'extend/wxpay/cert2/',
        'sslkey_path'=>'extend/wxpay/cert2/',
    ],
    'publicNumber'=>[
        'appid'=>'wx7bf42b3018c65d73',
        'appsecret'=>'538a762c26b893c4c22e11752689743a',
        'mchkey'=>'3Q3DXErcU3yyfR29oVt8ATVJyvliRiV4',
        'mch_id'=>'1509959201',
        'trade_type'=>'JSAPI',
        'sslcert_path'=>'extend/wxpay/cert4/',
        'sslkey_path'=>'extend/wxpay/cert4/',
    ],
    'mch' => [
        'wx57436ba4503ec088' => [
            'appid'=>'wx57436ba4503ec088',
            'appsecret'=>'b012b6db8835afbedb1f38d909005219',
            'key'=>'3Q3DXErcU3yyfR29oVt8ATVJyvliRiV4',
            'mchid'=>'1509959201',
            'apiclient_cert'=>\Env::get('root_path') . 'extend/wxpay/cert4/',
            'apiclient_key'=>\Env::get('root_path') . 'extend/wxpay/cert4/',
        ],
        'wxf629fdbdaa441335' => [
            'appid'=>'wxf629fdbdaa441335',
            'appsecret'=>'d71fea6094e28ff456c6454c8e2a609d',
            'key'=>'3Q3DXErcU3yyfR29oVt8ATVJyvliRiV4',
            'mchid'=>'1511601321',
            'apiclient_cert'=>\Env::get('root_path') . 'extend/wxpay/cert2/',
            'apiclient_key'=>\Env::get('root_path') . 'extend/wxpay/cert2/',
        ],
        'wx7bf42b3018c65d73' => [
            'appid'=>'wx7bf42b3018c65d73',
            'appsecret'=>'538a762c26b893c4c22e11752689743a',
            'key'=>'3Q3DXErcU3yyfR29oVt8ATVJyvliRiV4',
            'mchid'=>'1509959201',
            'apiclient_cert'=>\Env::get('root_path') . 'extend/wxpay/cert4/',
            'apiclient_key'=>\Env::get('root_path') . 'extend/wxpay/cert4/',
        ],
        //以前的小程序主体账号,先配合退款
        'wx9b9de204bb1d8ed1' => [
            'appid'=>'wx9b9de204bb1d8ed1',
            'appsecret'=>'067169276a2a652a073f25783aa3663f',
            'key'=>'3Q3DXErcU3yyfR29oVt8ATVJyvliRiV4',
            'mchid'=>'1509959201',
            'apiclient_cert'=>\Env::get('root_path') . 'extend/wxpay/cert4/',
            'apiclient_key'=>\Env::get('root_path') . 'extend/wxpay/cert4/',
        ],
    ],
    'isNowSendType'=>['after_sale_apply', 'cancel_order_user', 'coupon_receive',],
    //微信模板消息
    'template' => [
        'mp' => [
            'cancel_order_user' => [
                'des' => '用户主动取消订单提醒',
                'template_id' => 'QMLXLl7IoiRwOv33bP2oZmTv1m4fUzpVLsOOoRX42HY',
            ],
            'cancel_order_timeout' => [
                'des' => '订单超时取消提醒',
                'template_id' => 'QMLXLl7IoiRwOv33bP2oZmTv1m4fUzpVLsOOoRX42HY',
            ],
            'order_wait_pay' => [
                'des' => '订单待支付提醒',
                'template_id' => 'EJCN3ANcPPXQUJ0PG1D83EA4tgd6wVg7wOg80BBUnyI',
            ],
            'pintuan_user_short' => [
                'des' => '拼单人数不足提醒',
                'template_id' => '9De234MI6v7Kw-pUqGGbELS8TO87mrraVslgyL1YFoU',
            ],
            'pintuan_success' => [
                'des' => '拼单成功通知',
                'template_id' => 'tlO9DtQZeiH8NJjI3T7QnCpo5JbvzXI3N7yfBKW61ck',
            ],
            'order_deliver' => [
                'des' => '订单发货通知',
                'template_id' => 'H6YTjSHTUc0quWzjkgO-nWPimyzJpMHFu2Bc64TIyRo',
            ],
            'coupon_receive' => [
                'des' => '优惠券领取成功通知',
                'template_id' => '',
            ],
            'coupon_timeout' => [
                'des' => '优惠券即将过期提醒',
                'template_id' => '',
            ],
            'after_sale_apply' => [
                'des' => '商品申请售后提醒',
                'template_id' => 'RH0xtZDCh8XZqm0sNKdy_XJLwSRioh1J5_s2u8i4lEs',
            ],
            'after_sale_audit' => [
                'des' => '商品申请售后成功or失败',
                'template_id' => 'RH0xtZDCh8XZqm0sNKdy_XJLwSRioh1J5_s2u8i4lEs',
            ],
            'after_sale_goods_audit' => [
                'des' => '商品申请售后(退款退货)成功or失败',
                'template_id' => 'RH0xtZDCh8XZqm0sNKdy_XJLwSRioh1J5_s2u8i4lEs',
            ],
        ],
        'wx' => [
            'pintuan_order_success' => [
                'des' => '拼单成功通知',
                'template_id' => '-HQLQfroPVBJoN9RgkHaYHloJMdd4H9acZ4Jzhq07ww',
                'path'=>'pages/orderDetails/main?orderId=',
            ],
            'order_cancel' => [
                'des' => '订单取消通知',
                'template_id' => 'KbY0W_YvJPaOMussIfLkCtbRUvCCktxlAk3YvDq2LD8',
                'path'=>'pages/goodsDetails/main?goodsId=',
            ],
            'pintuan_num_insufficient' => [
                'des' => '拼单人数不足提醒',
                'template_id' => 'YK6SLzwBsrAoaaKOfsZgaP_4lUkH2RkdyEZ8bYz3MMg',
                'path'=>'pages/orderDetails/main?orderId=',
            ],
            'order_wait_pay' => [
                'des' => '订单待支付通知',
                'template_id' => 'atOthNVecL2gGGAVapxrzdoBXIpFxVG5MHn6WLpCOzo',
                'path'=>'pages/orderDetails/main?orderId=',
            ],
            'deliver_goods' => [
                'des' => '商品发货通知',
                'template_id' => '99Uj-mRY580ouNjJviqvcuC_5YFOKHh92klSvW3631w',
                'path'=>'pages/logisticsInfo/main?orderId=',
            ],
            'refund_schedule' => [
                'des' => '售后审核',
                'template_id' => 'yXldWdg155fdTvqxaDc9eov73gfnOQ0hzUjT6nOWf4o',
                'path'=>'pages/refundDetails/main?refundId=',
            ],
        ],
    ],
    'templateArray'=>[ //需要重新组合字段的模板
        'KbY0W_YvJPaOMussIfLkCtbRUvCCktxlAk3YvDq2LD8',
        'yXldWdg155fdTvqxaDc9eov73gfnOQ0hzUjT6nOWf4o',
    ],
];