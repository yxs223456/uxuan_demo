<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/9/14
 * Time: 11:49
 */
namespace wstmart\common\service;

use wstmart\common\model\UserScores as CMUS;

class UserScores
{
    public function getList($userId, $offset, $pageSize)
    {
        $userScoresModel = new CMUS();
        $count = $userScoresModel->getCount(['userId'=>$userId]);
        $list = $userScoresModel->getList($userId, $offset, $pageSize);
        $scoreType = config('web.score_type');
        $userScores = [];
        foreach ($list as $item) {
            $timeStamp = strtotime($item['createTime']);
            $date = date('Y-m-d', $timeStamp);
            if (!isset($userScores[$date])) {
                $userScores[$date] = [
                    'date' => $date,
                    'list' => [
                        [
                            'dataType' => $scoreType[$item['dataType']],
                            'score' => $item['score'],
                            'scoreType' => $item['scoreType'],
                            'time' => date('H:i:s', $timeStamp),
                            'inAccount' => 1,
                        ],
                    ],
                ];
            } else {
                $userScores[$date]['list'][] = [
                    'dataType' => $scoreType[$item['dataType']],
                    'score' => $item['score'],
                    'scoreType' => $item['scoreType'],
                    'time' => date('H:i:s', $timeStamp),
                    'inAccount' => 1,
                ];
            }
        }
        $userScores = array_values($userScores);
        return [
            'total' => $count,
            'userScores' => $userScores,
            'offset' => $offset,
            'pageSize' => $pageSize,
        ];
    }
}