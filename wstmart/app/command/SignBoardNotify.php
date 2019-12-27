<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/10/20
 * Time: 17:11
 */

namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\facade\Log;
use wstmart\common\helper\Dingding;
use wstmart\common\service\News;

class SignBoardNotify extends Command
{

    protected function configure()
    {
        $this->setName('SignBoardNotify')->setDescription('签到提醒');
    }

    protected function execute(Input $input, Output $output)
    {
        $begin = time();
        $times = 0;
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
            $message = '【每日优选】' . date('Y-m-d H:i:s') . '||签到提醒||错误信息：' . $message;
            Log::write($message, 'error');
            $dingMessage = json_encode(['msgtype' => 'text', 'text' => ['content' => $message]]);
            $names = ['陈小军'];
            Dingding::senMessage($dingMessage, $names);
            sleep(60);
            throw $e;
        }
        $output->writeln('times = ' . $times . ' end in ' . date('Y-m-d H:i:s'));
        $output->writeln("sleep 10s");
        sleep(10);
    }

    protected function doWork(Output $output)
    {
        $signUser = $this->getSignUser();
        if (empty($signUser) || !is_array($signUser) ){
            $output->writeln('none data todo');
            return;
        }
        foreach ($signUser as $user) {
            Db::startTrans();
            try {
                (new News())->signBoard($user);
                Db::commit();
                $this->currentSuccess++;
                $output->writeln('userId id=' . $user['userId']. ' success');
            } catch (\Throwable $e) {
                Db::rollback();
                $message = json_encode([
                    'exceptionClass' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE);
                $message = '【每日优选】' . date('Y-m-d H:i:s') . '||签到提醒foreach循环脚本异常||错误信息：' . $message;
                Log::write($message, 'error');
                $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
                $names = ['陈小军'];
                Dingding::senMessage($dingMessage, $names);
            }
        }
    }

    protected function getSignUser()
    {
        $where['lastSignDate'] = date('Y-m-d',strtotime("-1 day"));
        $where['userStatus'] = 1;
        $where['dataFlag'] = 1;
        $where['signRemind'] = 0;
        $signUser = Db::name('users')->where($where)->field('userId, lastSignDate, continuousSignDays')->select();
        return $signUser;
    }
}