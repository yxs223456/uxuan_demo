<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/11/12
 * Time: 20:06
 */
namespace app\console;

;
use wstmart\common\service\Mission;
use wstmart\common\model\Mission as CMMission;
use wstmart\common\helper\SwooleConnect;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class AsyncTask extends Command
{

    protected $server;

    protected function configure()
    {
        // setName 设置命令行名称
        // setDescription 设置命令行描述
        $this->setName('task:start')->setDescription('Start Task Server!');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->server = new \swoole_server('0.0.0.0', 9501);

        // server 运行前配置
        $this->server->set([
            'worker_num' => 4,
            'daemonize' => false,
            'task_worker_num' => 4,  # task 进程数
        ]);

        // 注册回调函数
        $this->server->on('Start', [$this, 'onStart']);
        $this->server->on('Connect', [$this, 'onConnect']);
        $this->server->on('Receive', [$this, 'onReceive']);
        $this->server->on('Task', [$this, 'onTask']);
        $this->server->on('Finish', [$this, 'onFinish']);
        $this->server->on('Close', [$this, 'onClose']);

        $this->server->start();
    }

    // 主进程启动时回调函数
    public function onStart(\swoole_server $server)
    {
        echo "Start\n";
    }

    // 建立连接时回调函数
    public function onConnect(\swoole_server $server, $fd, $from_id)
    {
        echo "Connect\n";
    }

    // 收到信息时回调函数
    public function onReceive(\swoole_server $server, $fd, $from_id, $data)
    {

        //投递异步任务connectSwoole
        $task_id = $server->task($data);

        $returnData = false === $task_id ? $this->swooleReturn(-2, "异步任务调用失败") : $this->swooleReturn(200);

        //将受到的客户端消息再返回给客户端
        $server->send($fd, $returnData);

    }

    // 异步任务处理函数
    public function onTask(\swoole_server $server, $task_id, $from_id, $missionCode)
    {
        $missionService = new Mission();
        $mission = [];
        if($missionCode == config("constants.initMissionListKey")) {
            $taskType = "initMissionListKey";
            $taskData = [];
        } else {
            $mission = $missionService
                ->findByMissionCode($missionCode);
            if(empty($mission)) {
                return false;
            }
            if($mission["status"] != config("enum.missionStatus.waiting.value")) {
                return false;
            }
            $taskData = json_decode($mission["param"], true);
            $taskType = $taskData["task_type"];
        }
        Db::startTrans();
        try {

            switch ($taskType) {
                //初始化未完成任务
                case "initMissionListKey":
                    // 获取未完成任务列表
                    $missionList = $missionService->getWaitingList();
                    foreach ($missionList as $mission) {
                        if($mission["mission_type"] == config("enum.missionType.setTimeout.value")) {
                            (new SwooleConnect)->setTimeoutTask($mission["execute_time"], $mission["mission_code"]);
                        } else {
                            (new SwooleConnect)->asyncTask($mission["mission_code"]);
                        }

                    }
                    break;

                // 发布任务 支付后模板消息、message通知粉丝
                case "fanliCallback":
                    $this->fanliCallback($taskData['order_id']);
                    break;
                default:
                    break;
            }
            if(!empty($mission)) {

                // 更新任务已完成
                $missionData["status"] = config("enum.missionStatus.end.value");
                $missionData["end_time"] = date('Y-m-d H:i:s');

                $mission->updateByIdAndData($mission["id"], $missionData);

                // redis 删除任务编号
                /*$redis = new Client(config("constants.redis_config"));
                $redis->srem("missionList", $mission["mission_code"]);*/

            }
            Db::commit();

        } catch (\Throwable $exception) {
            Db::rollback();
        }
        $server->finish(json_encode($taskData));
    }

    // 异步任务完成通知 Worker 进程函数
    public function onFinish(\swoole_server $server, $task_id, $result)
    {
        echo "onFinish\n";
    }

    // 关闭连时回调函数
    public function onClose(\swoole_server $server, $fd, $from_id)
    {

        //删除redis所有此类型
        $missionService = new Mission();

        $missionList = $missionService->getWaitingList(
            config("enum.missionType.async.value"));

        if(count($missionList) > 0) {

            // redis 删除任务编号
            $redis = new Client(config("constants.redis_config"));

            foreach ($missionList as $mission) {
                $redis->srem("missionList", $mission["mission_code"]);
            }

        }

        echo "Close\n";
    }

    //调用返回
    protected function swooleReturn($code, $msg = "", $data = "")
    {
        $returnData["code"] = $code;
        $returnData["msg"] = $msg;
        $returnData["data"] = $data;

        return json_encode($returnData);
    }

    protected function fanliCallback($orderId)
    {
        $order = DB::name('orders')->alias('o')
            ->leftJoin('order_goods og', 'o.orderId=og.orderId')
            ->leftJoin('goods g', 'og.goodsId=g.goodsId')
            ->where('o.orderId', $orderId)
            ->field('o.orderNo,og.goodsId,g.goodsImg,g.goodsName,o.pid,o.createTime,o.milliCreateTime,
            o.realTotalMoney,og.goodsNum')
            ->find();
        if (empty($order)) {
            return;
        }
        $orderInfo = [
            'goodsId'=>$order['goodsId'],
            'goodsImg'=>$order['goodsImg'],
            'goodsName'=>$order['goodsName'],
            'pid'=>$order['pid'],
            'yxCreateTime'=>$order['createTime'],
            'createUnixtime'=>$order['milliCreateTime'],
        ];
        if ($order['goodsNum'] == 1) {
            $orderInfo['orderNo'] = $order['orderNo'];
            $orderInfo['realTotalMoney'] = $order['realTotalMoney'];
            $result = fanliCurl($orderInfo);
            if ($result === false) {
            //    $this->fanliCallbackFail($orderId, $orderInfo, 1);
            }
        } else {

        }
    }
}