<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/11/12
 * Time: 20:08
 */
namespace wstmart\common\service;

use wstmart\common\model\Mission as MissionModel;

class Mission
{

    public function __construct()
    {
        $this->currentModel = new MissionModel();
    }

    /**
     * 获取所有未完成任务
     * @param $missionType
     * @return array|\PDOStatement|string|\think\Collection
     */
    public function getWaitingList($missionType = null)
    {

        $map['status'] = config("enum.missionStatus.waiting.value");

        if ($missionType != null) {
            $map["mission_type"] = $missionType;
        }

        $list = $this->currentModel
            ->where($map)
            ->select();

        return $list;

    }

    /**
     * 根据任务编号查找
     * @param $missionCode
     * @return array|null|\PDOStatement|string|\think\Model
     */
    public function findByMissionCode($missionCode)
    {

        $map['mission_code'] = $missionCode;

        $info = $this->currentModel
            ->where($map)
            ->find();

        return $info;

    }
}