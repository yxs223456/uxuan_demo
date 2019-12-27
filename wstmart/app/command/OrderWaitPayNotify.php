<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/6
 * Time: 15:49
 */
namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use wstmart\common\service\WxTemplateNotify as WTN;
use think\facade\Log;
use wstmart\common\helper\Dingding;

class OrderWaitPayNotify extends Command
{
    protected $maxAliveTime = 3600;
    protected $continue = true;

    protected function configure()
    {
        $this->setName('OrderWaitPayNotify')->setDescription('订单待支付提醒');
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
                $message = '【每日优选】' . date('Y-m-d H:i:s') . '||订单待支付提醒脚本异常||错误信息：' . $message;
                Log::write($message, 'error');
                $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
                $names = ['杨秀山'];
                Dingding::senMessage($dingMessage, $names);
                sleep(60);
                throw $e;
            }
            $output->writeln('times = ' . $times . ' end in ' . date('Y-m-d H:i:s'));
            $output->writeln("sleep 2s");
            sleep(2);
            if (time() > $begin + $this->maxAliveTime) {
                $this->continue = false;
            }
        }
    }

    protected function doWork()
    {
        $waitPayNotifyOrders = $this->getWaitPayNotifyOrders();
        foreach ($waitPayNotifyOrders as $waitPayNotifyOrder) {
            if ($waitPayNotifyOrder['orderSrc'] == 5) {
                $this->mpNotify($waitPayNotifyOrder);
            }
        }
    }

    protected function getWaitPayNotifyOrders()
    {
        $orderTimeoutNotifyConfig = config('web.order_timeout_notify');
        $waitPayNotifyOrders = DB::name('orders')
            ->where('orderStatus', -2)
            ->where('createTime', '<' , date('Y-m-d H:i:s', time() - $orderTimeoutNotifyConfig))
            ->where('createTime', '>' , date('Y-m-d H:i:s', time() - $orderTimeoutNotifyConfig - 300))
            ->select();
        return $waitPayNotifyOrders;
    }

    protected function mpNotify($waitPayNotifyOrder)
    {
        $wtn = new WTN();
        $map = [
            'userId' => $waitPayNotifyOrder['userId'],
            'type' => 'orderWaitPay',
            'targetId' => $waitPayNotifyOrder['orderId'],
        ];
        $notifyMes = $wtn->getTemplateNotify($map);
        if ($notifyMes) {
            return;
        }
        $wtn->orderWaitPay($waitPayNotifyOrder);
    }
}