<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/7/2
 * Time: 11:30
 */

namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use wstmart\common\model\Goods as G;
use wstmart\common\helper\Redis;
use wstmart\common\helper\Dingding;
use think\facade\Log;

class GetCatsGoodsList extends Command
{
    protected $maxAliveTime = 3600;
    protected $continue = true;
    protected $currentSuccess;
    protected $totalSuccess = 0;

    protected function configure()
    {
        $this->setName('GetCatsGoodsList')->setDescription('分类商品列表');
    }

    /*
     * 添加首页分类商品列表到缓存
     */
    protected function execute(Input $input, Output $output)
    {
        $begin = time();
        $times = 0;
        while($this->continue) {
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
                $message = '【每日优选】' . date('Y-m-d H:i:s') . '||商品分类缓存====||错误信息：' . $message;
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
            if (time() > $begin + $this->maxAliveTime) {
                $this->continue = false;
            }
        }
    }

    public function doWork(Output $output)
    {
        $rs = WSTGoodsCats(0);
        $catsName = config('enum.CatsGoodsList');
        if (empty($rs)) {
            $output->writeln('获取分类失败------');
            sleep(30);
        }
        $g = new G();
        $time = get5MinuteDateKey();
        $redis_conf = config('redis.');
        $r = new Redis($redis_conf);
        foreach ($rs as $key=>$item) {
            try {
                $catsKey = $catsName.'_'.$item['catId'].'_'.$time;
                if ($r->zCard($catsKey)>0) continue;
                $val = $item['catId'].'_%';
                $where[0] = ['goodsCatIdPath', 'like', $val];
                $row = $g->getGoodsListAll($where);
                $keys = $r->keys($catsName.'_*');
                $this->removeCache($keys, $r);
                if (empty($row['list'])) {
                    $output->writeln('没有查到所需要的数据------'.$item['catId']);
                    continue;
                }
                foreach ($row['list'] as $k=>$v) {
                    $r->zAdd($catsKey, $k, json_encode($v));
                    $output->writeln('添加成功------' . $key);
                }
            } catch (\Throwable $e) {
                $dd = new Dingding();
                $dd->senMessage(json_encode(['msgtype'=>'text','text'=>['content'=>'GetCatsGoodsList----'.$e->getMessage()]]), ['陈小军']);
            }
        }
    }

    public function removeCache($keys,Redis $r)
    {
        if (is_array($keys)) {
            foreach ($keys as $k=>$v) {
                $time = explode('_', $v);
                if (strtotime($time[2]) <= time() - 172800) {
                    $r->del($v);
                }
            }
        }
    }

}
