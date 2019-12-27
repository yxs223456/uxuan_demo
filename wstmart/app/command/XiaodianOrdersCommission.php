<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/10/23
 * Time: 15:37
 */
namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use wstmart\common\helper\Dingding;

class XiaodianOrdersCommission extends Command
{
    protected $maxAliveTime = 3600;
    protected $continue = true;

    protected function configure()
    {
        $this->setName('XiaodianOrdersCommission')->setDescription('小店分佣计算');
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
                $message = '【每日优选】' . getAppEnvironment() . date('Y-m-d H:i:s') . '||小店分佣计算||错误信息：' . $message;
                $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
                $names = ['杨秀山', '陈小军'];
                Dingding::senMessage($dingMessage, $names);
                sleep(60);
                throw $e;
            }
            $output->writeln('times = ' . $times . ' end in ' . date('Y-m-d H:i:s'));
            $output->writeln("sleep 60s");
            sleep(60);
            if (time() > $begin + $this->maxAliveTime) {
                $this->continue = false;
            }
        }
    }

    protected function doWork()
    {
        $orders = $this->getOrders();
        $commissionOrders = [];
        $inValidOrders = [];
        foreach ($orders as $order) {
            if ($order['afterSaleStatus'] == 0 && strtotime($order['receiveTime']) <= time() - config('web.refund_time')) {
                $commissionOrders[] = $order;
            }
            if ($order['afterSaleStatus'] == 3 || $order['afterSaleStatus'] == 5) {
                $commissionOrders[] = $order;
            }
            if ($order['afterSaleStatus'] == 2) {
                $inValidOrders[] = $order;
            }
        }
        $this->dealCommissionOrders($commissionOrders);
        $this->dealInvalidOrders($inValidOrders);
    }

    protected function getOrders()
    {
        $orders = DB::name('xiaodian_orders')->alias('xo')
            ->leftJoin('orders o', 'o.orderId=xo.orderId')
            ->where('xo.isValid', 1)
            ->where('xo.isSettlement', 0)
            ->where('o.orderStatus', 2)
            ->field('xo.id,o.afterSaleStatus,o.receiveTime')
            ->select();
        return $orders;
    }

    protected function dealCommissionOrders($commissionOrders)
    {
        foreach ($commissionOrders as $commissionOrder) {
            $info = [
                'isSettlement' => 1,
                'settlementTime' => date('Y-m-d H:i:s'),
            ];
            DB::name('xiaodian_orders')->where('id', $commissionOrder['id'])->update($info);
        }
    }

    protected function dealInvalidOrders($inValidOrders)
    {
        foreach ($inValidOrders as $inValidOrder) {
            $info = [
                'isValid' => 0,
            ];
            DB::name('xiaodian_orders')->where('id', $inValidOrder['id'])->update($info);
        }
    }
}