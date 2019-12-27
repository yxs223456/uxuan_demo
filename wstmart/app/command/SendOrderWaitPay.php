<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/11/1
 * Time: 14:27
 */

namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use wstmart\common\service\WxTemplateNotify as WTN;
use think\facade\Log;
use wstmart\common\helper\Dingding;
use wstmart\common\service\News;
use wstmart\common\helper\Redis;

class SendOrderWaitPay extends command
{
    protected function configure()
    {
        $this->setName('SendOrderWaitPay')->setDescription('订单待支付提醒');
    }

    protected function execute(Input $input, Output $output)
    {
        $times = 0;
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
            $names = ['杨秀山', '陈小军'];
            Dingding::senMessage($dingMessage, $names);
            sleep(60);
            throw $e;
        }
        $output->writeln('times = ' . $times . ' end in ' . date('Y-m-d H:i:s'));
        $output->writeln("sleep 100s");
        sleep(100);
    }

    protected function doWork()
    {
        $redisConfig = config('redis.');
        $redis = new Redis($redisConfig);
        $key = config('web.sendOrderWaitPay').'_';
        $waitPayNotifyOrders = $this->getWaitPayNotifyOrders();
        foreach ($waitPayNotifyOrders as $waitPayNotifyOrder) {
            $isSend = $redis->get($key.$waitPayNotifyOrder['orderId']);
            if ($isSend && $isSend==1) continue;
            (new News())->orderWaitPay($waitPayNotifyOrder);
            $redis->set($key.$waitPayNotifyOrder['orderId'], 1, 1800);
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

}