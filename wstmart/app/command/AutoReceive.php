<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/8/7
 * Time: 9:37
 */
namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use wstmart\common\model\Orders as O;
use think\facade\Log;
use wstmart\common\helper\Dingding;

class AutoReceive extends Command
{
    protected function configure()
    {
        $this->setName('AutoReceive')->setDescription('自动确认收货脚本');
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
            $message = '【每日优选】' . getAppEnvironment() . date('Y-m-d H:i:s') . '||自动确认收货脚本异常||错误信息：' . $message;
            Log::write($message, 'error');
            $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
            $names = ['杨秀山'];
            Dingding::senMessage($dingMessage, $names);
        }
        $output->writeln('end in ' . date('Y-m-d H:i:s'));
        $output->writeln("sleep 1h");
        sleep(3600);
    }

    /**
     * 发货后15天自动确认收货
     */
    protected function doWork()
    {
        $autoReceiveOrders = $this->getAutoReceiveOrders();
        foreach ($autoReceiveOrders as $autoReceiveOrder) {
            $orderModel = (new O())->getOrderById($autoReceiveOrder['orderId']);
            if ($orderModel->orderStatus != 1 || $orderModel->afterSaleStatus == 2) {
                unset($orderModel);
                continue;
            }
            $orderModel->orderStatus = 2;
            $orderModel->receiveTime = date('Y-m-d H:i:s');
            $orderModel->save();
            unset($orderModel);
        }
    }

    protected function getAutoReceiveOrders()
    {
        $autoReceiveTime = config('web.order_receive_time');
        $autoReceiveOrders = DB::name('orders')
            ->where('orderStatus', 1)
            ->where('afterSaleStatus', '<>', 2)
            ->where('deliveryTime', '<=', date('Y-m-d H:i:s', time() - $autoReceiveTime))
            ->field('orderId')
            ->select();
        return $autoReceiveOrders;
    }
}