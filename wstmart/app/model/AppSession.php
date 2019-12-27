<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/6/25
 * Time: 17:57
 */
namespace wstmart\app\model;

class AppSession extends Base
{
    public function updateToken($userId, $tokenId, $type)
    {
        $session = $this->where([
                'userId' => $userId,
                'type' => $type
            ])->find();
        if (!empty($session)) {
            $session->tokenId = $tokenId;
            $session->save();
        } else {
            $sessionInfo = [
                'userId' => $userId,
                'tokenId' => $tokenId,
                'startTime' => date('Y-m-d H:i:s'),
                'type' => $type
            ];
            $this->insert($sessionInfo);
        }
    }

    public function getTokenByTokenId($tokenId)
    {
        $userToken = $this->where("tokenId='{$tokenId}'")->find();
        return $userToken;
    }

    public function getTokenInfo($where, $field)
    {
        $rs = $this->where($where)->field($field)->find();
        return $rs;
    }
}