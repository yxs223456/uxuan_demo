<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/11
 * Time: 15:15
 */
namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use wstmart\common\model\PintuanUsers as PU;
use wstmart\common\model\PintuanOrders as PO;
use wstmart\common\model\Orders as O;
use wstmart\common\model\Goods as G;
use wstmart\common\service\Orders as SO;
use think\Db;
use think\facade\Log;
use wstmart\common\helper\Dingding;
use wstmart\common\service\News;

class OrderTimeout extends Command
{
    protected $maxAliveTime = 3600;
    protected $continue = true;
    protected $currentSuccess;
    protected $totalSuccess = 0;

    protected function configure()
    {
        $this->setName('OrderTimeout')->setDescription('处理订单超时未支付');
    }

    protected function execute(Input $input, Output $output)
    {
        $begin = time();
        $times = 0;
        while($this->continue) {
            $times++;
            $output->writeln('times = ' . $times . ' begin in ' . date('Y-m-d H:i:s'));
            try {
                $this->currentSuccess = 0;
                $this->doWork($output);
                $this->totalSuccess += $this->currentSuccess;
                $output->writeln('currentSuccess=' . $this->currentSuccess . ',totalSuccess=' . $this->totalSuccess);
            } catch (\Throwable $e) {
                $message = json_encode([
                    'exceptionClass' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE);
                $message = '【每日优选】' . date('Y-m-d H:i:s') . '||处理订单超时未支付脚本异常||错误信息：' . $message;
                Log::write($message, 'error');
                $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
                $names = ['杨秀山'];
                Dingding::senMessage($dingMessage, $names);
                sleep(60);
                throw $e;
            }
            $output->writeln('times = ' . $times . ' end in ' . date('Y-m-d H:i:s'));
            $output->writeln("sleep 1s");
            sleep(1);
            if (time() > $begin + $this->maxAliveTime) {
                $this->continue = false;
            }
        }
    }

    protected function doWork(Output $output)
    {
        $timeoutOrders = $this->getTimeoutOrders();
        if (!empty($timeoutOrders)) {
            $pintuanUserModel = new PU();
            $pintuanOrderModel = new PO();
            $orderModel = new O();
            foreach ($timeoutOrders as $orderId) {
                $order = $orderModel->getOrderById($orderId);
                $orderGoods = $orderModel->getOrderGoodsByOrderId($orderId);
                $goodsModel = new G();
                $goods = $goodsModel->getGoodsById($orderGoods['goodsId']);
                $order->orderStatus = -6;
                $goods->goodsStock += $orderGoods['goodsNum'];
                $goods->saleNum -= $orderGoods['goodsNum'];
                if ($order->isPintuan == 1) {
                    $pintuanUser = $pintuanUserModel->getPintuanUserByOrderId($orderId);
                    $pintuanOrder = $pintuanOrderModel->getPintuanOrderByTuanNo($pintuanUser->tuanNo);
                    $pintuanUser->tuanStatus = -2;
                    $pintuanUser->dataFlag = 0;
                    $pintuanOrder->saleNum -= 1;
                    $pintuanOrder->needNum += 1;
                }
                Db::startTrans();
                try {
                    $order->save();
                    $goods->save();
                    if ($order->isPintuan == 1) {
                        $pintuanUser->save();
                        $pintuanOrder->save();
                    }
                    if ($orderGoods['goodsSpecId'] != 0) {
                        DB::name('goods_specs')->inc('specStock', $orderGoods['goodsNum']);
                        DB::name('goods_specs')->dec('saleNum', $orderGoods['goodsNum']);
                    }
                    if ($order->userCouponId>0) {
                        $orderModel->updateCouponsUseStatus($order->userCouponId, $order->userId, 0, '', '');
                    }
                    Db::commit();
                    (new SO())->timeoutNotify($order, $orderGoods);
                    (new News())->cancelNotify($order);
                    $this->currentSuccess++;
                    $output->writeln('orders id=' . $orderId . ' timeout');
                } catch (\Throwable $e) {
                    Db::rollback();
                    $message = json_encode([
                        'exceptionClass' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                    ], JSON_UNESCAPED_UNICODE);
                    $message = '【每日优选】' . date('Y-m-d H:i:s') . '||处理订单超时未支付foreach循环脚本异常||错误信息：' . $message;
                    Log::write($message, 'error');
                    $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
                    $names = ['杨秀山'];
                    Dingding::senMessage($dingMessage, $names);
                }
            }
        }
    }

    protected function getTimeoutOrders()
    {
        $orderTimeout = config('web.order_timeout');
        $delay = 30;
        $timeoutOrders = DB::name('orders')
            ->where('orderStatus', -2)
            ->where('createTime', '<= time', date('Y-m-d H:i:s', time()-$orderTimeout-$delay))
            ->column('orderId');
        return $timeoutOrders;
    }
}