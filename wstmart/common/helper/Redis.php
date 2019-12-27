<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/2
 * Time: 14:35
 */
namespace wstmart\common\helper;

use think\cache\driver\Redis as thinkRedis;
class Redis extends thinkRedis
{
    public static function getRedis()
    {
        $redisConfig = config('redis.');
        $redis = new self($redisConfig);
        return $redis;
    }

    public function __call($method, $params)
    {
        return call_user_func_array([$this->handler, $method], $params);
    }

    /**
     * 向有序集合中添加一个元素
     * @param $key
     * @param $score
     * @param $value
     */
    public function zAdd($key, $score, $value)
    {
        $this->handler->zAdd($key, $score, $value);
    }

    /**
     * 计算有序集合中元素的数量
     * @param $key
     * @return mixed
     */
    public function zCard($key)
    {
        return $this->handler->zCard($key);
    }

    /**
     *指定区间内，带有分数值的有序集成员的列表
     */
    public function zRange($key, $start, $end)
    {
        return $this->handler->zRange($key, $start, $end);
    }

    public function keys($pattern)
    {
        return $this->handler->keys($pattern);
    }

    /**
     *删除指定的key
     */
    public function del($key)
    {
        return $this->handler->del($key);
    }
}