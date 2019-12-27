<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/11/10
 * Time: 12:01
 */

/**
 * 使用长连接方式连接swoole
 * @param $hosts
 * @param $port
 * @param $data
 * @return mixed
 */
namespace wstmart\common\helper;

class SwooleConnect
{
    public function connectSwoole($hosts, $port, $data) {
        // 使用长连接方式连接swoole
        $client = new \swoole_client(SWOOLE_SOCK_TCP | SWOOLE_KEEP);

        // 连接到服务器
        $client->connect($hosts, $port);

        // 向服务器发送数据
        $client->send($data);

        // 接收服务器返回数据
        $res = $client->recv();

        // 关闭连接
        $client->close(true);

        return $res;
    }

    /**
     * 异步消息处理
     * @param $missionCode
     * @return mixed
     */
    function asyncTask($missionCode) {

        //判断redis里是否存在同样missionCode
        /*$redis = new \Predis\Client(config("constants.redis_config"));

        $result = $redis->sismember("missionList", $missionCode);

        if(!$result) {

            $redis->sadd("missionList", array($missionCode));*/

            $hosts = "127.0.0.1";
            $port = "9501";

            return $this->connectSwoole($hosts, $port, $missionCode);
        /*}

        return false;*/

    }

    /**
     * 延迟任务
     * @param $endTime
     * @param $missionCode
     * @return mixed
     */
    public function setTimeoutTask($endTime, $missionCode)
    {

        //判断redis里是否存在同样missionCode
        /*  $redis = new \Predis\Client(config("constants.redis_config"));

          $result = $redis->sismember("missionList", $missionCode);

          if(!$result) {

              $redis->sadd("missionList", array($missionCode));*/

        $hosts = "127.0.0.1";
        $port = "9509";

        $secondsGap = strtotime($endTime) - time();

        if ($secondsGap <= 0) {
            $secondsGap = 5;
        }

        $data = json_encode([
            "time" => $secondsGap * 1000,
            "mission_code" => $missionCode
        ]);

        return $this->connectSwoole($hosts, $port, $data);
        /* }

         return false;*/
    }
}
