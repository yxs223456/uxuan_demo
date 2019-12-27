<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/11/2
 * Time: 19:24
 */

namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Env;
use think\facade\Log;
use wstmart\common\helper\Dingding;
use wstmart\common\helper\AliyunOss;

class OssUpload extends Command
{
    protected $maxAliveTime = 3600;
    protected $continue = true;
    protected $currentSuccess;
    protected $totalSuccess = 0;

    protected function configure()
    {
        $this->setName('OssUpload')->setDescription('oss上传文件转移');
    }

    protected function execute(Input $input, Output $output)
    {
        $begin = time();
        $times = 0;
        $times++;
        $output->writeln('times = ' . $times . ' begin in ' . date('Y-m-d H:i:s'));
        try {
            $this->doWork();
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
            $message = '【每日优选】' . date('Y-m-d H:i:s') . '||上传文件转移||错误信息：' . $message;
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
        $this->continue = false;
    }

    public function doWork()
    {
        $data = [];
        //没有文件的目录放在数组里面
        $array = ['goods','shops', 'users','shopconfigs','image','friendlinks','complains','brands','appraises'];
        $rootPath = \Env::get('root_path').'upload/';
        $files = $this->getFile();
        foreach ($files as $k=>$item) {
            if ($k<2 ) continue;
            if (in_array($item, $array)) continue;
            $filename = $this->getFile($item.'/');
            if (empty($filename)) continue;
            foreach ($filename as $key=>$items) {
                if ($key<2) continue;
                if ($items=='txt.txt') continue;
                if (file_exists($rootPath.$item.'/'.$items)) {
                    $img = $this->getFile($item.'/'.$items.'/');
                    foreach ($img as $keyImg=>$valueImg) {
                        if ($keyImg<2) continue;
                        if ($valueImg=='txt.txt') continue;
                        $ossImgs = $this->ossUpload($valueImg, $item.'/'.$items.'/'.$valueImg);
                        $data[$item][$items][$valueImg] = $ossImgs;
                    }
                }else{
                    continue;
                }
            }
        }
        file_put_contents(\Env::get('root_path').'runtime/img.txt', json_encode($data));
    }

    public function getFile($dirPath='')
    {
        $rootPath = \Env::get('root_path').'upload/'.$dirPath;
        $files = scandir($rootPath);
        return $files;
    }

    public function ossUpload($filename, $path)
    {
        $ossGallery = (new AliyunOss())->putObject($filename, file_get_contents(config('web.image_domain').'/upload/'.$path))['url'];
        return $ossGallery;
    }

    public function getGoodsSpecImg()
    {
        $rs = Db::name('spec_items')->field('itemId,itemImg')->seletct();
        return $rs;
    }

}