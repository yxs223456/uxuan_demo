<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/10
 * Time: 10:07
 */
namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\facade\Log;
use wstmart\common\helper\Dingding;

class PintuanSuccess extends Command
{
    protected $maxAliveTime = 3600;
    protected $continue = true;
    protected $currentSuccess;
    protected $totalSuccess = 0;

    protected function configure()
    {
        $this->setName('PintuanSuccess')->setDescription('处理拼团成功');
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
//                $this->doWork($output);
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
                $message = '【每日优选】' . date('Y-m-d H:i:s') . '||处理拼团成功脚本异常||错误信息：' . $message;
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
        $waitSuccessPintuan = $this->waitSuccessPintuan();
        if (empty($waitSuccessPintuan) || !is_array($waitSuccessPintuan) ){
            $output->writeln('none data todo');
            return;
        }
        foreach ($waitSuccessPintuan as $tuanOrder) {
            $success = $this->pintuanIsSuccess($tuanOrder);
            if (!$success) {
                continue;
            }
            Db::startTrans();
            try {
                DB::name('pintuan_orders')->where('id', $tuanOrder['id'])
                    ->update(['tuanStatus'=>1, 'successTime'=>date('Y-m-d H:i:s')]);
                $this->afterSuccess($success);
                Db::commit();
                $this->currentSuccess++;
                $output->writeln('pintuan_order id=' . $tuanOrder['id']. ' success');
            } catch (\Throwable $e) {
                Db::rollback();
                $message = json_encode([
                    'exceptionClass' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE);
                $message = '【每日优选】' . date('Y-m-d H:i:s') . '||处理拼团成功foreach循环脚本异常||错误信息：' . $message;
                Log::write($message, 'error');
                $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
                $names = ['杨秀山'];
                Dingding::senMessage($dingMessage, $names);
            }
        }
    }

    protected function waitSuccessPintuan()
    {
        $waitSuccessPintuan = DB::name('pintuan_orders')
            ->where('needNum', 0)
            ->where('tuanStatus', 0)
            ->select();
        return $waitSuccessPintuan;
    }

    protected function pintuanIsSuccess(array $tuanOrder)
    {
        $payOrder = DB::name('pintuan_users')
            ->where('tuanNo', $tuanOrder['tuanNo'])
            ->where('tuanStatus', 1)
            ->where('isPay', 1)
            ->where('refundStatus', 0);
        $payOrder1 = clone $payOrder;
        $payCount = $payOrder1->count();
        if ($payCount < $tuanOrder['tuanNum']) {
            return false;
        }
        return $payOrder->select();
    }

    protected function afterSuccess(array $success)
    {
        foreach ($success as $item) {
            DB::name('pintuan_users')
                ->where('id', $item['id'])
                ->update(['tuanStatus' => 2]);
            DB::name('orders')
                ->where('orderId', $item['orderId'])
                ->update(['orderStatus'=>0]);
        }
    }
}