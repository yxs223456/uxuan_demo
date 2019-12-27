<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/10
 * Time: 11:39
 */
namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use wstmart\common\service\Pintuan as P;
use think\facade\Log;
use wstmart\common\helper\Dingding;

class PintuanFail extends Command
{
    protected $maxAliveTime = 3600;
    protected $continue = true;

    protected function configure()
    {
        $this->setName('PintuanFail')->setDescription('处理拼团失败');
    }

    protected function execute(Input $input, Output $output)
    {
        $begin = time();
        $times = 0;
        while($this->continue) {
            $times++;
            $output->writeln('times = ' . $times . ' begin in ' . date('Y-m-d H:i:s'));
            try {
                $this->doWork($output);
            } catch (\Throwable $e) {
                $message = json_encode([
                    'exceptionClass' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE);
                $message = '【每日优选】' . date('Y-m-d H:i:s') . '||处理拼团失败脚本异常||错误信息：' . $message;
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

    protected function doWork(Output $output)
    {
        $failPintuan = $this->failPintuan();
        if (!is_array($failPintuan) || !count($failPintuan)){
            $output->writeln('none data todo');
            return;
        }
        foreach ($failPintuan as $tuanOrder) {
            $this->dealFail($tuanOrder['tuanNo'], $tuanOrder['tuanId']);
        }
    }

    protected function failPintuan()
    {
        $orderTimeout = config('web.order_timeout');
        $failPintuan = DB::name('pintuan_orders')
            ->where('needNum', '>', 0)
            ->where('tuanStatus', 0)
            ->where("createdAt <= (" . time() . " - `tuanTime` - $orderTimeout)")
            ->select();
        return $failPintuan;
    }

    protected function dealFail($tuanNo, $tuanId)
    {
        $pintuanUsers = DB::name('pintuan_users')
            ->where('tuanNo', $tuanNo)
            ->where('isPay', 1)
            ->select();
        $pintuanService = new P();
        foreach ($pintuanUsers as $pintuanUser) {
            $pintuanService->failRefund($pintuanUser);
        }
        DB::name('pintuan_orders')->where('tuanNo', $tuanNo)->update(['tuanStatus'=>-1]);
        Db::name('pintuans')->where('tuanId', $tuanId)->setDec('tuanSaleNum', 1);
    }
}