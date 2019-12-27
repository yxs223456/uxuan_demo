<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/8/6
 * Time: 11:04
 */
namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use wstmart\common\model\OrderRefunds as OS;
use wstmart\common\model\Orders as MO;
use wstmart\common\service\Orders as O;
use think\facade\Log;
use wstmart\common\helper\Dingding;

class AfterSale extends Command
{

    protected function configure()
    {
        $this->setName('AfterSale')->setDescription('售后申请7天后自动退款');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('begin in ' . date('Y-m-d H:i:s'));
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
            $message = '【每日优选】' . getAppEnvironment() . date('Y-m-d H:i:s') . '||售后自动退款脚本异常||错误信息：' . $message;
            Log::write($message, 'error');
            $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
            $names = ['杨秀山'];
            Dingding::senMessage($dingMessage, $names);
        }
        $output->writeln('end in ' . date('Y-m-d H:i:s'));
        $output->writeln("sleep 1h");
        sleep(3600);
    }

    protected function doWork()
    {
        $this->autoRefund();
        $this->autoRefuse();
    }

    /**
     * 售后申请7天不处理，自动给用户退款
     */
    protected function autoRefund()
    {
        $autoRefundApply = $this->getAutoRefundApply();
        foreach ($autoRefundApply as $item) {
            $orderRefundModel = (new OS())->get($item['id']);
            if ($orderRefundModel->refundStatus != 0) {
                continue;
            }
            $this->doRefund($orderRefundModel);
        }
    }

    protected function getAutoRefundApply()
    {
        $autoRefundTime = config('web.order_refund_time');
        $autoRefundApply = DB::name('order_refunds')
            ->where('refundStatus', 0)
            ->where('createTime', '<=', date('Y-m-d H:i:s', time() - $autoRefundTime))
            ->select();
        return $autoRefundApply;
    }

    protected function doRefund(\think\model $orderRefundModel)
    {
        Db::startTrans();
        try {
            $orderRefundModel->refundStatus = 1;
            $orderRefundModel->shopConsentTime = date('Y-m-d H:i:s');
            $orderRefundModel->save();
            $orderModel = (new MO())->getOrderById($orderRefundModel->orderId);
            $orderModel->afterSaleStatus = 2;
            $orderModel->save();
            (new O())->dealOrderRefund($orderRefundModel->id);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $errMessage = json_encode([
                'exceptionClass' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            $message = '【每日优选】' . date('Y-m-d H:i:s') . '||售后自动退款失败||退款id=' . $orderRefundModel->id . '||错误信息：' . $errMessage;
            Log::write($message, 'error');
            $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
            $names = ['杨秀山'];
            Dingding::senMessage($dingMessage, $names);
        }
    }

    /**
     * 商家同意退货退款7天用户不退货，售后申请自动拒绝关闭
     */
    protected function autoRefuse()
    {
        $autoRefuseApply = $this->getAutoRefuseApply();
        foreach ($autoRefuseApply as $item) {
            $orderRefundModel = (new OS())->get($item['id']);
            if ($orderRefundModel->refundStatus != 4) {
                continue;
            }
            $this->doRefuse($orderRefundModel);
        }
    }

    protected function getAutoRefuseApply()
    {
        $autoRefundTime = config('web.order_refund_time');
        $autoRefundApply = DB::name('order_refunds')
            ->where('refundStatus', 4)
            ->where('expressId', 0)
            ->where('shopConsentTime', '<=', date('Y-m-d H:i:s', time() - $autoRefundTime))
            ->select();
        return $autoRefundApply;
    }

    protected function doRefuse(\think\model $orderRefundModel)
    {
        Db::startTrans();
        try {
            $orderRefundModel->refundStatus = -1;
            $orderRefundModel->shopRejectReason = '超时未向商家退货';
            $orderRefundModel->save();
            $orderModel = (new MO())->getOrderById($orderRefundModel->orderId);
            $orderModel->afterSaleStatus = 3;
            $orderModel->save();
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $errMessage = json_encode([
                'exceptionClass' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            $message = '【每日优选】' . date('Y-m-d H:i:s') . '||售后自动拒绝失败||退款id=' . $orderRefundModel->id . '||错误信息：' . $errMessage;
            Log::write($message, 'error');
            $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
            $names = ['杨秀山'];
            Dingding::senMessage($dingMessage, $names);
        }
    }
}