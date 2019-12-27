<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/11/1
 * Time: 14:08
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
use wstmart\common\service\News;
use wstmart\common\helper\Redis;

class SendPintuanPeopleLess extends Command
{
    protected $maxAliveTime = 3600;
    protected $continue = true;

    protected function configure()
    {
        $this->setName('SendPintuanPeopleLess')->setDescription('拼单人数不足消息推送');
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
            $message = '【每日优选】' . date('Y-m-d H:i:s') . '||拼单人数不足提醒脚本异常||错误信息：' . $message;
            Log::write($message, 'error');
            $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
            $names = ['杨秀山', '陈小军'];
            Dingding::senMessage($dingMessage, $names);
            sleep(60);
            throw $e;
        }
        $output->writeln('times = ' . $times . ' end in ' . date('Y-m-d H:i:s'));
        $output->writeln("sleep 60s");
        sleep(60);
        $this->continue = false;
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
        $redisConfig = config('redis.');
        $redis = new Redis($redisConfig);
        $key = config('web.sendPintuanPeopleLess').'_';
        $pintuanUsersModel = new PU();
        $pintuanUsers = $pintuanUsersModel->getPintuanUsersByTuanNo($waitNotifyPintuan['tuanNo']);
        foreach ($pintuanUsers as $pintuanUser) {
            $pintuanTime = (int)floor((time() - $waitNotifyPintuan['createdAt'])/3600);
            $key.= $pintuanUser['id'].'_'.$pintuanTime;
            $isSend = $redis->get($key);
            if ($isSend && $isSend==1) continue;
            (new News())->pintuanPeopleLess($pintuanUser, $waitNotifyPintuan);
            $redis->set($key, 1, 3600);
        }
    }
}