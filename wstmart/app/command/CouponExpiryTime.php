<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/9/7
 * Time: 10:55
 */

namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\facade\Log;
use wstmart\common\helper\Dingding;
use wstmart\common\model\Coupons as C;
use wstmart\common\service\WxTemplateNotify as WTN;
use wstmart\common\service\News;

class CouponExpiryTime extends Command
{
    protected $maxAliveTime = 3600;
    protected $continue = true;
    protected $currentSuccess;
    protected $totalSuccess = 0;

    protected function configure()
    {
        $this->setName('CouponExpiryTime')->setDescription('优惠券过期提醒');
    }

    protected function execute(Input $input, Output $output)
    {
        $begin = time();
        $times = 0;
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
            $message = '【每日优选】' . date('Y-m-d H:i:s') . '||优惠券过期处理====||错误信息：' . $message;
            Log::write($message, 'error');
            $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
            $names = ['陈小军'];
            Dingding::senMessage($dingMessage, $names);
            sleep(60);
            throw $e;
        }
        $output->writeln('times = ' . $times . ' end in ' . date('Y-m-d H:i:s'));
        $output->writeln("sleep 1s");
        sleep(1);
        $this->continue = false;

    }

    protected function doWork(Output $output)
    {
        $c = new C();
        $userInfo = $c->getUserGroup();
        if (!empty($userInfo)) {
            foreach ($userInfo as $k=>$v) {
                $info = $c->getUserCouponData($v['userId']);
                if (empty($info)) continue;
                foreach ($info as $item=>$items) {
                    $date = strtotime($items['endDate'])-time();
                    if ($date > 0 && bcdiv($date, 86400)<3) {
                        Db::startTrans();
                        try{
                            //(new WTN())->couponTimeout($v['userId'],'coupon_timeout',$items['couponId']);
                            (new News())->redPacketsExpired($v['userId'],$items['couponId']);
                            Db::commit();
                            $output->writeln('用户id='.$v['userId'].'couponId id=' . $items['couponId'] . ' coupon_timeout');
                        } catch (\Throwable $e) {
                            Db::rollback();
                            $message = json_encode([
                                'exceptionClass' => get_class($e),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                                'code' => $e->getCode(),
                                'message' => $e->getMessage(),
                            ], JSON_UNESCAPED_UNICODE);
                            $message = '【每日优选】' . date('Y-m-d H:i:s') . '||优惠券过期处理||错误信息：' . $message;
                            Log::write($message, 'error');
                            $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
                            $names = ['陈小军'];
                            Dingding::senMessage($dingMessage, $names);
                        }
                    } else {
                        continue;
                    }
                }
            }
        }
    }
}