<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/23
 * Time: 14:13
 */
namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use wstmart\common\helper\WeixinPay as WP;
use wstmart\common\struct\WxRefund;
use wstmart\common\model\OrderRefunds as ORF;
use think\facade\Log;
use wstmart\common\helper\Dingding;

class DealWxRefund extends Command
{
    protected $maxAliveTime = 3600;
    protected $continue = true;

    protected function configure()
    {
        $this->setName('DealWxRefundNotSuccess')->setDescription('处理微信退款');
    }

    protected function execute(Input $input, Output $output)
    {
        $begin = time();
        $times = 0;
        while($this->continue) {
            $times++;
            $output->writeln('times = ' . $times . ' begin in ' . date('Y-m-d H:i:s'));
            try {
                $this->doWork();
            } catch (\Throwable $e) {
                $message = json_encode([
                    'exceptionClass' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE);
                $message = '【每日优选】' . date('Y-m-d H:i:s') . '||微信退款脚本异常||错误信息：' . $message;
                Log::write($message, 'error');
                $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
                $names = ['杨秀山', '陈小军'];
                Dingding::senMessage($dingMessage, $names);
                sleep(60);
                throw $e;
            }
            $output->writeln('times = ' . $times . ' end in ' . date('Y-m-d H:i:s'));
            $output->writeln("sleep 10s");
            sleep(10);
            if (time() > $begin + $this->maxAliveTime) {
                $this->continue = false;
            }
        }
    }

    protected function doWork()
    {
        $notSureRefundOrders = $this->getNotSureRefundOrders();

        $orf = new ORF();
        foreach ($notSureRefundOrders as $notSureRefundOrder) {
            $this->checkRefundIsSubmit($notSureRefundOrder);
            $refundResult = $this->checkRefundIsSuccess($notSureRefundOrder);
            if ($refundResult->refund_status_0=='SUCCESS') {
                if ($notSureRefundOrder['type']=='afterSaleRefund') {
                    $data = ['refundTradeNo'=>$notSureRefundOrder['out_refund_no'], 'refundTime'=>$refundResult->refund_success_time_0];
                    $orf->updateRefundData(['id'=>$notSureRefundOrder['targetId']], $data);
                }
            }
        }
    }

    protected function getNotSureRefundOrders()
    {
        $notSureRefundOrders = DB::name('wx_refund_orders')
            ->where('isRefund', 0)
            ->select();
        return $notSureRefundOrders;
    }

    protected function checkRefundIsSubmit($notSureRefundOrder)
    {
        if ($notSureRefundOrder['result_code'] === 'SUCCESS') {
            return;
        } elseif ($notSureRefundOrder['err_code_des'] === '订单已全额退款') {
            return;
        }
        $weixinPay = new WP();
        $wxRefundInfo = [
            'appid' => $notSureRefundOrder['appid'],
            'transaction_id' => $notSureRefundOrder['transaction_id'],
            'out_refund_no' => $notSureRefundOrder['out_refund_no'],
            'total_fee' => $notSureRefundOrder['total_fee'],
            'refund_fee' => $notSureRefundOrder['refund_fee'],
            'refund_desc' => $notSureRefundOrder['refund_desc'],
        ];
        $result = $weixinPay->wxRefund($wxRefundInfo);
        $refundResult = new WxRefund($result);
        $refundUpdate = [
            'mch_id' => $refundResult->mch_id,
            'refund_id' => $refundResult->refund_id,
            'result_code' => $refundResult->result_code,
            'err_code' => $refundResult->err_code,
            'err_code_des' => $refundResult->err_code_des,
        ];
        DB::name('wx_refund_orders')
            ->where('id', $notSureRefundOrder['id'])
            ->update($refundUpdate);
        if (!isset($result['return_code']) || $result['return_code'] !== 'SUCCESS' ||
            !isset($result['result_code']) || $result['result_code'] !== 'SUCCESS') {
            $message = '【每日优选】' . date('Y-m-d H:i:s') . '||微信退款失败||退款id=' . $notSureRefundOrder['id'] . '||微信返回：' . json_encode($result, JSON_UNESCAPED_UNICODE);
            Log::write($message, 'error');
            $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
            $names = ['杨秀山', '陈小军'];
            Dingding::senMessage($dingMessage, $names);
        }
    }

    protected function checkRefundIsSuccess($notSureRefundOrder)
    {
        $weixinPay = new WP();
        $wxRefundInfo = [
            'appid' => $notSureRefundOrder['appid'],
            'out_refund_no' => $notSureRefundOrder['out_refund_no'],
        ];
        $refundQueryResult = $weixinPay->wxRefundQuery($wxRefundInfo);
        $refundStatus = null;
        if (isset($refundQueryResult['refund_status_0']) && $refundQueryResult['refund_status_0'] == 'SUCCESS') {
            $refundStatus = true;
        }
        $refundQueryResultStruct = new WxRefund($refundQueryResult);
        $refundResultUpdate = [
            'refund_status' => $refundQueryResultStruct->refund_status,
            'refund_status_0' => $refundQueryResultStruct->refund_status_0,
            'refund_recv_accout_0' => $refundQueryResultStruct->refund_recv_accout_0,
            'refund_recv_accout' => $refundQueryResultStruct->refund_recv_accout,
            'refund_recv_account_0' => $refundQueryResultStruct->refund_recv_account_0,
            'refund_recv_account' => $refundQueryResultStruct->refund_recv_account,
            'refund_success_time_0' => $refundQueryResultStruct->refund_success_time_0,
            'refund_success_time' => $refundQueryResultStruct->refund_success_time,
            'isRefund' => $refundStatus ? 1 : 0,
        ];
        DB::name('wx_refund_orders')
            ->where('id', $notSureRefundOrder['id'])
            ->update($refundResultUpdate);
        return $refundQueryResultStruct;
    }
}