<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/10/15
 * Time: 11:22
 */

namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use wstmart\app\model\Users as U;
use think\facade\Log;
use wstmart\common\helper\Dingding;
use wstmart\common\service\WxTemplateNotify as WTN;

class SendFavorableNews extends Command
{
    protected $maxAliveTime = 3600;
    protected $continue = true;

    protected function configure()
    {
        $this->setName('SendFavorableNews')->setDescription('自动发送消息');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('times = begin in ' . date('Y-m-d H:i:s'));
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
            $message = '【每日优选】' . date('Y-m-d H:i:s') . '||自动发送优惠消息失败||错误信息：' . $message;
            Log::write($message, 'error');
            $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
            $names = ['陈小军'];
            Dingding::senMessage($dingMessage, $names);
            sleep(10);
            throw $e;
        }
        $output->writeln('times =  end in ' . date('Y-m-d H:i:s'));
        $output->writeln("sleep 10s");
        sleep(10);
    }

    protected function doWork()
    {
        $favorableNewsInfo = $this->getFavorableNewInfo();
        if (empty($favorableNewsInfo)) {
            sleep(60);
            return false;
        }
        $data = [];
        $userGroupId = (new U())->getUserGroupId();
        foreach ($favorableNewsInfo as $key=>$item) {
            foreach ($userGroupId as $userKey=>$userItem) {
                $data[$userKey]['userId'] = $userItem;
                $data[$userKey]['title'] = '优惠通知';
                $data[$userKey]['noticeType'] = 'favourableNews';
                $data[$userKey]['text'] = json_encode([
                    'title'=>$item['title'],
                    'text'=>$item['text'],
                    'img'=>addImgDomain($item['favorablenewsPath']),
                    'accessType'=>$item['accessType'],
                    'moduleUrl'=>$item['moduleUrl']
                ]);
                $data[$userKey]['createTime'] = time();
                $data[$userKey]['newsType'] = 1;
                $data[$userKey]['newsId'] = $item['id'];
            }
            $this->doSend($data,$item['id']);
            (new WTN())->sendXmPushToAll(mb_substr($item['text'],0,20).'...', '优惠通知', ['title'=>$item['title'], 'text'=>$item['text'], 'accessType'=>$item['accessType'], 'moduleUrl'=>$item['moduleUrl']]);
        }
    }

    protected function doSend($data, $id)
    {
        Db::startTrans();
        try {
            $this->insertUserFavorableNew($data);
            $this->insertFavorableNewSendTime($id);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $errMessage = json_encode([
                'exceptionClass' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            $message = '【每日优选】' . date('Y-m-d H:i:s') . '||优惠消息发送失败||消息id=' . $id . '||错误信息：' . $errMessage;
            Log::write($message, 'error');
            $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
            $names = ['陈小军'];
            Dingding::senMessage($dingMessage, $names);
        }
    }

    protected function getFavorableNewInfo()
    {
        $rs = Db::name('favorable_news')->where(['newsType'=>1,'sendStatus'=>0,'dataFlag'=>1])->select();
        return $rs;
    }

    protected function insertUserFavorableNew($data)
    {
        $rs = Db::name('user_news')->data($data)->limit(10)->insertAll();
        return $rs;
    }

    protected function insertFavorableNewSendTime($id)
    {
        $info['sendTime'] = date('Y-m-d H:i:s');
        $info['sendStatus'] = 1;
        return Db::name('favorable_news')->where('id', $id)->update($info);
    }
}
