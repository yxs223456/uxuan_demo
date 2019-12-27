<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/2
 * Time: 11:29
 */
namespace wstmart\app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use wstmart\common\helper\Redis;
use wstmart\app\model\Goods;
use think\facade\Log;
use wstmart\common\helper\Dingding;

class CacheRecommendGoodsList extends Command
{
    protected $maxAliveTime = 3600;
    protected $continue = true;

    protected function configure()
    {
        $this->setName('CacheRecommendGoodsList')->setDescription('缓存优选商品列表');
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
                $message = '【每日优选】' . date('Y-m-d H:i:s') . '||缓存优选商品列表脚本异常||错误信息：' . $message;
                Log::write($message, 'error');
                $dingMessage = json_encode(['msgtype'=>'text','text'=>['content'=>$message]]);
                $names = ['杨秀山'];
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
        $redisConfig = config('redis.');
        $redis = new Redis($redisConfig);
        $this->cacheNotShieldList($redis, $output);
        $this->cacheShieldList($redis, $output);
    }

    protected function cacheNotShieldList(\wstmart\common\helper\Redis $redis, Output $output)
    {
        $lastKey = 'recommend_goods_list_' . get5MinuteDateKey();
        if ($redis->zCard($lastKey)) {
            $output->writeln($lastKey . " exist already");
            return;
        }
        $this->cacheNotShieldGoods($redis, $lastKey, $output);
    }

    protected function cacheShieldList(\wstmart\common\helper\Redis $redis, Output $output)
    {
        $lastKey = 'recommend_goods_shield_list_' . get5MinuteDateKey();
        if ($redis->zCard($lastKey)) {
            $this->deleteTwoDaysAgoKeys($redis);
            $output->writeln($lastKey . " exist already");
            return;
        }
        $this->cacheShieldGoods($redis, $lastKey, $output);
    }

    protected function deleteTwoDaysAgoKeys(Redis $redis)
    {
        $pattern = 'recommend_goods_list_*';
        $keys = $redis->keys($pattern);
        if (is_array($keys)) {
            foreach ($keys as $key) {
                $date = substr($key, 21);
                if (strtotime($date) <= time() - 172800) {
                    $redis->del($key);
                }
            }
        }

        $pattern = 'recommend_goods_shield_list_*';
        $keys = $redis->keys($pattern);
        if (is_array($keys)) {
            foreach ($keys as $key) {
                $date = substr($key, 28);
                if (strtotime($date) <= time() - 172800) {
                    $redis->del($key);
                }
            }
        }
    }

    protected function cacheNotShieldGoods(Redis $redis, $lastKey, Output $output)
    {
        $goodsModel = new Goods();
        $goodsList = $goodsModel->getAllRecommendGoods();
        if (is_array($goodsList) && count($goodsList)) {
            foreach ($goodsList as $key=>$goods) {
                $redis->zAdd($lastKey, $key, json_encode($goods, JSON_UNESCAPED_UNICODE));
            }
            $output->writeln($lastKey . " created");
        }
    }

    protected function cacheShieldGoods(Redis $redis, $lastKey, Output $output)
    {
        $goodsModel = new Goods();
        $goodsList = $goodsModel->getAllRecommendShieldGoods();
        if (is_array($goodsList) && count($goodsList)) {
            foreach ($goodsList as $key=>$goods) {
                $redis->zAdd($lastKey, $key, json_encode($goods, JSON_UNESCAPED_UNICODE));
            }
            $output->writeln($lastKey . " created");
        }
    }
}