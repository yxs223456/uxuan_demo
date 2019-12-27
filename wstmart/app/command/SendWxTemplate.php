<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/4
 * Time: 16:09
 */
namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use wstmart\common\helper\WxTemplateNotify;
use think\facade\Log;
use wstmart\common\helper\Dingding;

class SendWxTemplate extends Command
{
    protected $maxAliveTime = 3600;
    protected $continue = true;

    protected function configure()
    {
        $this->setName('SendWxTemplate')->setDescription('发送微信模板消息');
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
                $message = '【每日优选】' . date('Y-m-d H:i:s') . '||发送微信模板消息脚本异常||错误信息：' . $message;
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

    protected function doWork()
    {
        $notSendMes = $this->getNotSendMes();
        if (empty($notSendMes) || (is_array($notSendMes) && !count($notSendMes))) {
            return;
        }
        foreach ($notSendMes as $notSendMessage) {
            $this->doSend($notSendMessage);
        }
    }

    protected function getNotSendMes()
    {
        return DB::name('wx_template_notify')->where('isSend',0)->select();
    }

    protected function doSend($notSendMessage)
    {
        $wxTemplateNotify = new WxTemplateNotify();
        $accessToken = getUxuanAccessToken($notSendMessage['appid']);
        if ($notSendMessage['wxClient'] === 'miniPrograms') {
            try {
                $params = json_decode($notSendMessage['params'], true);
                $rs = $wxTemplateNotify->mpSend($accessToken, $params['touser'], $params['template_id'], $params['form_id'],
                    $params['data'], $params['page'], $params['emphasis_keyword']);
                DB::name('wx_template_notify')->where('id', $notSendMessage['id'])->update(['isSend'=>1, 'wxReturn'=>$rs]);
            } catch (\Throwable $e) {
                $message = json_encode([
                    'exceptionClass' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE);
                $message = '【每日优选】' . date('Y-m-d H:i:s') . '||小程序发送退款脚本异常||错误信息：' . $message;
                $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
                $names = ['杨秀山'];
                Dingding::senMessage($dingMessage, $names);
            }
        }
    }
}