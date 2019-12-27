<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/6/21
 * Time: 15:45
 */
namespace wstmart\common\exception;

class AppException extends \Exception
{
    const COM_MOBILE_ERR = [2, '手机号码格式错误'];
    const COM_PARAMS_EMPTY = [3, '请求参数为空'];
    const COM_PARAMS_ERR = [4, '请求参数错误'];
    const COM_URL_ERROR = [5, '页面不存在'];
    const COM_MOBILE_EXISTS = [6, '手机号已存在'];

    const COM_TOKEN_ERROR = [8, 'token为空'];
    const COM_USERS_ERROR = [7, '用户不存在'];
    const COM_USERS_EXISTS = [9, '用户已存在'];

    const COM_REQUEST_ERR = [10, '请求异常，请重新发起请求'];
    const COM_ARRAY_ERR = [11, '请先转化数组格式'];

    const SMS_CODE_TYPE_NOT_EXISTS = [1000, '验证码类型不存在'];
    const SMS_CODE_IP_EXCEED_LIMIT = [1001, '获取次数超限，明天再来~'];
    const SMS_CODE_PHONE_EXCEED_LIMIT = [1002, '获取次数超限，明天再来~'];
    const SMS_CODE_ERR = [1003, '验证码错误'];
    const SMS_CODE_OVERDUE = [1004, '验证码过期，请重新获取'];
    const SMS_CODE_EMPTY = [1005, '请输入验证码'];
    const SMS_CODE_BUSINESS_LIMIT_CONTROL = [1006, '验证码不可频繁获取~'];

    const WECHAT_TOKEN_ERR = [2001, '微信token验证失败'];
    const WECHAT_USERINFO_ERR = [2002, '获取微信用户信息失败'];
    const WECHAT_PARAM_ERR = [2003, '微信参数错误'];
    const WECHAT_PARAM_SAVE_FAIL = [2004, '微信数据保存失败'];
    const WECHAT_AUTH_SUCCESS = [2005, '微信已授权'];
    const WECHAT_AUTH_BIND = [2006, '微信已绑定'];
    const USER_AUTH_BIND = [2009, '用户已绑定'];
    const PHONE_AUTH_BIND = [2008, '手机号已绑定'];
    const WECHAT_UNTIE_FAIL = [2007, '微信解绑失败'];

    const USER_FREEZE = [3000, '您的账号已被冻结'];
    const USER_NOT_LOGIN = [3001, '您还未登录'];
    const USER_TOKEN_ERR = [3001, '登录信息已过期,请重新登录'];
    const USER_ADDRESS_NAME_EMPTY = [3002, '请填写收货联系人'];
    const USER_ADDRESS_PHONE_ERR = [3003, '电话格式错误'];
    const USER_ADDRESS_PROVINCE_EMPTY = [3004, '请选择邮寄省份'];
    const USER_ADDRESS_CITY_EMPTY = [3005, '请选择邮寄城市'];
    const USER_ADDRESS_COUNTY_EMPTY = [3006, '请选择邮寄县区'];
    const USER_ADDRESS_ADDRESS_EMPTY = [3007, '详细地址不能为空'];
    const USER_ADDRESS_NOT_EXISTS = [3008, '收货地址不存在'];
    const USER_ADDRESS_DEFAULT_NOT_EXISTS = [3009, '默认收货地址不存在'];
    const USER_PHONE_EXISTS_ALREADY = [3010, '手机号已被其他账号使用'];
    const USER_NOT_EXISTS = [3011, '用户记录不存在'];
    const USER_TODAY_SIGN_ALREADY = [3012, '今日已签到'];
    const COM_MOBILE_BIND_FAIL = [3013, '手机号绑定失败'];
    const USER_ADDRESS_NUM_TOP = [3014, '地址数量达到上限'];
    const USER_TODAY_NOT_SIGN = [3015, '您今天还没有签到'];
    const USER_CONTINUES_SIGN_ERR = [3016, '连续签到条件未达标'];
    const USER_SING_LOTTERY_ALREADY = [3017, '今日已抽奖'];

    const GOODS_NOT_EXISTS = [4000, '商品不存在'];
    const GOODS_PINTUAN_NOT_EXISTS = [4001, '该商品不允许拼团'];
    const GOODS_STOCK_LITTLE = [4002, '商品库存不足'];
    const GOODS_SPEC_STOCK_LITTLE = [4003, '商品规格库存不足'];
    const GOODS_USER_NOT_EXISTS = [4004, '没有此拼团'];
    const GOODS_UNDERCARRIAGE = [4005, '商品已下架'];

    const DATA_INSERT_FAIL = [5000, '数据添加失败'];
    const DATA_UPDATE_FAIL = [5001, '数据修改失败'];
    const DATA_GET_FAIL = [5002, '数据获取失败'];

    const ORDER_NOT_EXISTS = [6000, '订单不存在'];
    const ORDER_GOODS_NOT_EXISTS = [6001, '订单商品不存在'];
    const ORDER_PINTUAN_NUMBER_ZERO = [6002, '该拼团已开团'];
    const ORDER_PINTUAN_NUMBER_NOT_ENOUGH = [6003, '拼团名额不足'];
    const ORDER_PINTUAN_OVER = [6004, '拼团已结束'];
    const ORDER_PINTUAN_ORDER_NOT_EXISTS = [6005, '找不到该拼团信息'];
    const ORDER_PINTUAN_JOIN_ALREADY = [6006, '您已经是该拼团成员了'];
    const ORDER_PINTUAN_BUY_ONLY_ONE = [6007, '拼团时只能购买一件商品'];
    const ORDER_PINTUAN_USER_NOT_EXISTS = [6008, '拼团记录不存在'];
    const ORDER_CREATE_FAIL = [6009, '订单生成失败'];
    const ORDER_STATUS_NOT_EXISTS = [6010, '订单状态无效'];
    const ORDER_PAY_ALREADY = [6011, '订单已支付'];
    const ORDER_TIMEOUT_ALREADY = [6012, '订单已超时'];
    const ORDER_NOTICE_DELIVER_ALREADY = [6013, '已提醒发货，请耐心等待'];
    const ORDER_DELETE_REFUSE = [6014, '当前状态下无法删除订单'];
    const ORDER_PINTUAN_REFUSE = [6015, '拼团没有成功不能申请'];
    const ORDER_NOT_PAY = [6016, '当前金额不能支付,请联系客服'];
    const ORDER_PINTUAN_NUM_OVERRUN = [6017, '购买数量超过上限'];

    const GOODS_APPRAISES_CONTENT_ERROR = [6021, '点评内容包含非法字符'];
    const GOODS_APPRAISES_EXISTS = [6017, '订单已点评'];
    const GOODS_APPRAISES_EXCHANGE = [6018, '订单状态已改变，请刷新订单后再尝试'];
    const GOODS_APPRAISES_INVALID = [6019, '无效的订单'];
    const ORDER_EXPIRE = [6020, '订单过期，无法操作'];
    const ORDER_REFUND_SECCESS = [6022, '该订单已退款'];
    const ORDER_PINTUAN_HEAD_NOT_EXISTS = [6023, '当前拼团无效，请参加其他团吧'];
    const ORDER_REFUND_NOT_EXISTS = [6024, '退款申请不存在'];
    const ORDER_REFUND_CANCEL_ALREADY = [6025, '用户已撤销售后申请'];
    const ORDER_TUAN_ERR = [6026, '参团规格与选择团规格不符'];
    const ORDER_REFUND_STATUS_CHANGE = [6027, '售后申请状态已改变'];
    const GOODS_APPRAISES_STAR_EMPTY = [6028, '请选择星级'];
    const GOODS_APPRAISES_CONTENT_EMPTY = [6028, '请填写商品评价'];

    const FREIGHT_FAIL = [7000, '运费计算错误'];
    const VERSION_UP_FAIL = [7001, '暂无版本更新'];
    const WX_TEMPLATE_EMPTY = [7002, '模板内容不能为空'];

    const WECHAT_NOTIFY_EMPTY = [8000, '微信支付结果返回值为空'];
    const WECHAT_NOTIFY_ERROR = [8001, '微信支付结果异常'];
    const WECHAT_UNIFIEDORDER_ERROR = [8002, '微信统一下单结果异常'];
    const WECHAT_UNIFIEDORDER_FAIL = [8003, '微信统一下单数据更新失败'];
    const WECHAT_GET_TOKEN_FAIL = [8004, '获取token失败'];
    const WECHAT_GET_USERINFO_FAIL = [8005, '获取微信用户信息是失败'];
    const WECHAT_INSERT_USERINFO_FAIL = [8006, '微信用户信息添加失败'];
    const WECHAT_INSERT_PAY_ORDERS_FAIL = [8007, '添加微信支付数据失败'];
    const WECHAT_INSERT_REFUND_ORDERS_FAIL = [8008, '添加微信退款数据失败'];
    const WECHAT_REFUND_TIME_EXPIR = [8009, '微信退款时间过期'];
    const WECHAT_MCH_NOT_EXISTS = [8010, '微信商户不存在'];
    const WECHAT_BIND_MINIPROGRAMS = [8011, '请先绑定小程序'];
    const WECHAT_BIND_PUBLIC = [8012, '请先绑定公众号'];
    const WECHAT_PAY_ORDERS_NOT_EXISTS = [8013, '微信支付订单不存在'];
    const WECHAT_PAY_ORDERS_NOT_UNTIE_BIND = [8014, '为了您的账户安全7天内不可解除关联的账号'];
    const WECHAT_TEMPLATE_PARAMS_EMPTY = [8015, '微信消息模板参数错误'];
    const WECHAT_NUMBER_IS_BIND = [8016, '此微信号已绑定过手机'];

    const WECHAT_CONFIG_EMPTY = [9000, '微信配置获取失败'];
    const WECHAT_MINPROGRAM_LOGIN_ILLEGALAESKEY = [-41001, 'encodingAesKey 非法'];
    const WECHAT_MINPROGRAM_LOGIN_ILLEGALIV = [-41002, '微信凭证获取失败'];
    const WECHAT_MINPROGRAM_LOGIN_ILLEGALBUFFER = [-41003, '解密后得到的buffer非法'];
    const WECHAT_MINPROGRAM_LOGIN_DECODEBASE64ERROR = [-41004, 'base64解密失败'];
    const WECHAT_MINPROGRAM_APPID_ERROR = [-41005, 'appid不一致'];

    const REFUND_TOTAL_MAX = [11000, '退款金额不能大于订单价'];
    const REFUND_ORDER_EMPTY = [11001, '退款订单不存在'];
    const REFUND_SHOP_NO_AGREE = [11002, '商家不同意退款'];
    const REFUND_SHOP_WAIT_DO = [11003, '等待商家处理'];
    const REFUND_INSERT_FAIL = [11004, '退款请求失败'];
    const REFUND_REASON_FAIL = [11005, '退款原因不存在'];
    const REFUND_WECHAT_ERROR = [11006, '退款异常'];
    const REFUND_APPLY_OVERTIME = [11007, '退款申请已超时'];
    const REFUND_STATUS_NOT_EXISTS = [11008, '当前状态不能申请退款'];
    const REFUND_ORDER_GOING = [11009, '退款申请中'];
    const REFUND_ORDER_NOT_COLLECT = [11010, '当前状态，不能收货'];
    const REFUND_ORDER_SHOP_NOT_AGREE = [11011, '商家还没有同意'];
    const REFUND_ORDER_SHOP_TIMEOUT = [11012, '同意申请超时'];
    const REFUND_ORDER_USER_REVOKE = [11013, '用户已撤销'];
    const REFUND_ORDER_ALREADY_PROCESSED = [11014, '申请已处理'];
    const REFUND_ORDER_NOT_AGAIN = [11015, '同一个订单不能重复申请'];
    const REFUND_ORDER_REVOKE = [11016, '当前状态不能撤销申请'];
    const REFUND_INFO_EMPTY = [11017, '退款信息不存在'];
    const REFUND_SATATUS_NOT_SUPPORT = [11018, '商家还未同意退款,请耐心等待'];
    const REFUND_SATATUS_UPDATE_OUT_TIME = [11019, '修改申请时间超时，不能再次申请，请联系平台操作'];
    const REFUND_NOT_EXPRESS_INFO = [11020, '请填写快递信息'];

    const COUPONS_TIME_EXPIRE = [21000, '红包不在使用期限内'];
    const COUPONS_MONEY_NO_MATCH = [21001, '没有达到使用条件金额'];
    const COUPONS_GET_FAIL = [21002, '领取红包失败'];
    const COUPONS_NUM_TOMAX = [21003, '该红包您的领取已达上限'];
    const COUPONS_NULL = [21004, '红包已领完'];
    const COUPONS_EXCHANGE_FAIL = [21005, '兑换红包失败'];
    const COUPONS_NO_USE = [21006, '该红包已失效'];
    const COUPONS_NO_HAVE = [21007, '没有此红包'];
    const COUPONS_NO_APPOINT = [21008, '不是指定的商品'];
    const COUPONS_NO_EXCHANGE = [21009, '此红包需要积分兑换'];
    const COUPONS_USE_EXISTS = [21010, '红包使用失败'];
    const COUPONS_NO_USE_EXISTS = [21011, '红包不可用，请重新选择'];
    const COUPONS_NO_USEOBJECTIDS = [21012, '该红包使用对象类型不是指定商品'];
    const COUPONS_DATE_EXPIRE = [21013, '该红包已过期'];
    const COUPONS_NEW_CAN_RECEIVE = [21014, '很抱歉，只有新手才能领取'];
    const COUPONS_NOT_EXCHANGE = [21015, '当前U币不能兑换此红包'];
    const COUPONS_NOT_DAILYLIMITNUM = [21016, '今日红包已兑换完，明天再来吧'];

    const GOODSAPPRAISES_INSERT_FAIL = [22001, '评价失败'];

    const WORD_OVERRUN = [23000, '字数超限,请按指示输入'];
    const GOODS_SPEC_ERROR = [23001, '商品与规格不对应'];


    public static function factory($errConst)
    {
        $e = new self($errConst[1], $errConst[0]);
        return $e;
    }
}