<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/14
 * Time: 15:13
 */
namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\facade\Log;
use wstmart\common\helper\Dingding;

class GetImageSpec extends Command
{
    protected $maxAliveTime = 3600;
    protected $continue = true;

    protected function configure()
    {
        $this->setName('GetImageSpec')->setDescription('获取图片尺寸');
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
                $message = '【每日优选】' . date('Y-m-d H:i:s') . '||获取图片尺寸脚本异常||错误信息：' . $message;
                Log::write($message, 'error');
                $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
                $names = ['杨秀山'];
                Dingding::senMessage($dingMessage, $names);
                sleep(60);
                throw $e;
            }
            $output->writeln('times = ' . $times . ' end in ' . date('Y-m-d H:i:s'));
            $output->writeln("sleep 60s");
            sleep(60);
            if (time() > $begin + $this->maxAliveTime) {
                $this->continue = false;
            }
        }
    }

    protected function doWork()
    {
        $images = DB::name('images')->where('imgInfo', '')->field('imgId,imgPath')->select();
        foreach ($images as $image) {
            try {
                $imageUrl = addImgDomain($image['imgPath']);
                $imgInfo = json_encode(getimagesize($imageUrl), JSON_UNESCAPED_UNICODE);
                DB::name('images')->where('imgId', $image['imgId'])->update(['imgInfo'=>$imgInfo]);
            } catch (\Throwable $e) {
                throw $e;
            }
        }
    }
}