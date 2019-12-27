<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/23
 * Time: 10:38
 */
namespace wstmart\common\struct;

class WxRefund extends Base
{
    public $appid;
    public $mch_id;
    public $transaction_id;
    public $out_refund_no;
    public $out_trade_no;
    public $refund_desc;
    public $refund_id;
    public $refund_fee;
    public $total_fee;
    public $result_code;
    public $err_code;
    public $err_code_des;
    public $refund_status_0;
    public $refund_status;
    public $refund_recv_accout_0;
    public $refund_recv_accout;
    public $refund_recv_account_0;
    public $refund_recv_account;
    public $refund_success_time_0;
    public $refund_success_time;

    protected $_types = [
        'appid' => 'string',
        'mch_id' => 'string',
        'transaction_id;' => 'string',
        'out_refund_no' => 'string',
        'out_trade_no' => 'string',
        'refund_desc' => 'string',
        'refund_id' => 'string',
        'refund_fee' => 'int',
        'total_fee' => 'int',
        'result_code' => 'string',
        'err_code' => 'string',
        'err_code_des' => 'string',
        'refund_status_0' => 'string',
        'refund_status' => 'string',
        'refund_recv_accout_0' => 'string',
        'refund_recv_accout' => 'string',
        'refund_recv_account_0' => 'string',
        'refund_recv_account' => 'string',
        'refund_success_time_0' => 'string',
        'refund_success_time' => 'string',
    ];
}