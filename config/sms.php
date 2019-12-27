<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/6/21
 * Time: 9:49
 */
return [
    'alidayu' => [      //阿里云短信配置
        'accessKeyId' => '',
        'accessKeySecret' => '',
    ],
    'verification_code_type' => [    //验证码用途
        'user_app_login',
        'user_h5_login',
        'user_wx_login',
        'user_mp_login',
        'bind_fans',
        'user_bind_mobile',
        'user_modify_bind_mobile',
    ],
    'verification_code_day_limit' => [
        'ip' => 50, //同一个ip每日最大验证码发放数量
        'phone' => 10, //同一个手机号每日最大验证码发放数量
    ],
    'verification_code_max_alive' => [
        'user_app_login' => 300,
        'user_wx_login' => 300,
        'user_mp_login' => 300,
        'user_h5_login' => 300,
        'bind_fans' => 300,
        'user_bind_mobile' => 300,
        'user_modify_bind_mobile' => 300,
    ],   //验证码有效期
    'user_app_login' => [
        'template' => 'SMS_140550204',
        'content' => '您的验证码是${code}，有效期5分钟',
    ],//用户app登录
    'user_wx_login' => [
        'template' => 'SMS_140550204',
        'content' => '您的验证码是${code}，有效期5分钟',
    ],//用户微信内登录
    'user_mp_login' => [
        'template' => 'SMS_140550204',
        'content' => '您的验证码是${code}，有效期5分钟',
    ],//用户小程序登录
    'user_h5_login' => [
        'template' => 'SMS_140550204',
        'content' => '您的验证码是${code}，有效期5分钟',
    ],//用户h5登录
    'bind_fans' => [
        'template' => 'SMS_140550204',
        'content' => '您的验证码是${code}，有效期5分钟',
    ],//绑定师徒关系
    'user_bind_mobile' => [
        'template' => 'SMS_140550204',
        'content' => '您的验证码是${code}，有效期5分钟',
    ],//用户绑定手机号
    'user_modify_bind_mobile' => [
        'template' => 'SMS_140550204',
        'content' => '您的验证码是${code}，有效期5分钟',
    ],//用户修改绑定手机号
    'user_news_template'=>[
        'couponExpire'=>'SMS_149100662',  //红包过期你
        'pintuanNumInsufficient'=>'SMS_148080245', //品单人数不足
        'refundSuccess'=>'SMS_148080247', //售后处理成功
        'refundFail'=>'SMS_148075360',  //售后处理失败
    ],//短信模板id
    'sms_sign'=>'每日优选',
];