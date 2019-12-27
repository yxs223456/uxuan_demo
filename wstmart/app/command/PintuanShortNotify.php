<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/6
 * Time: 16:44
 */
namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use wstmart\common\model\Orders as O;
use wstmart\common\model\WxPayOrders as WPO;
use wstmart\common\model\PintuanUsers as PU;
use wstmart\common\service\WxTemplateNotify as WTN;
use think\facade\Log;
use wstmart\common\helper\Dingding;

class PintuanShortNotify extends Command
{
    protected $maxAliveTime = 3600;
    protected $continue = true;

    protected function configure()
    {
        $this->setName('PintuanShortNotify')->setDescription('拼单人数不足提醒');
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
                $message = '【每日优选】' . date('Y-m-d H:i:s') . '||拼单人数不足提醒脚本异常||错误信息：' . $message;
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
        $waitNotifyPintuans = $this->getPintuanOrdersNotify();
        foreach ($waitNotifyPintuans as $waitNotifyPintuan) {
            $this->doPintuanShortNotify($waitNotifyPintuan);
        }
    }

    protected function getPintuanOrdersNotify()
    {
        $now = time();
        $hour1 = 3600;
        $hour4 = 4*3600;
        $hour8 = 8*3600;
        $hour23 = 23*3600;
        $waitNotifyPintuans = DB::name('pintuan_orders')
            ->where('tuanStatus', 0)
            ->where('needNum', '>', 0)
            ->where("(createdAt BETWEEN {$now}-{$hour1}-600 and {$now}-{$hour1}) or 
            (createdAt BETWEEN {$now}-{$hour4}-600 and {$now}-{$hour4}) or 
            (createdAt BETWEEN {$now}-{$hour8}-600 and {$now}-{$hour8}) or 
            (createdAt BETWEEN {$now}-{$hour23}-600 and {$now}-{$hour23})")
            ->select();
        return $waitNotifyPintuans;
    }

    protected function doPintuanShortNotify($waitNotifyPintuan)
    {
        $pintuanUsersModel = new PU();
        $pintuanUsers = $pintuanUsersModel->getPintuanUsersByTuanNo($waitNotifyPintuan['tuanNo']);
        foreach ($pintuanUsers as $pintuanUser) {
           $this->doPintuanUserNotify($pintuanUser, $waitNotifyPintuan);
        }
    }

    protected function doPintuanUserNotify($pintuanUser, $waitNotifyPintuan)
    {
        $order = (new O())->getOrderById($pintuanUser['orderId']);
        $wtn = new WTN();
        if ($order->payFrom === 'weixinpays') {
            $weixinPayOrder = (new WPO())->getWxPayOrderModel(['type'=>'orderPay','targetId'=>$order->orderId,'transaction_id'=>$order['tradeNo']]);
            if ($weixinPayOrder->wechatType === 'miniPrograms') {
                $wtn->pintuanShortNotify($pintuanUser, $waitNotifyPintuan, $weixinPayOrder);
            }
        }
    }


}