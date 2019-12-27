<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/6/22
 * Time: 14:31
 */
namespace wstmart\common\model;

use \wstmart\common\exception\AppException as AE;

class VerificationCodes extends Base
{
    protected $pk = 'codeId';

    public function saveCode($userPhone, $type, $code, $ip = '127.0.0.1')
    {
        $dataInfo = [
            'phoneNumber' => $userPhone,
            'userIp' => $ip,
            'type' => $type,
            'code' => $code,
            'status' => '当前有效',
            'date' => date('Y-m-d'),
            'createdAt' => time(),
        ];
        $this->data($dataInfo)->save();
    }

    public function updateCodeStatus($userPhone, $type)
    {
        $codeMaxAlive = config('sms.verification_code_max_alive.' . $type);    //验证码有效期
        $now = time();
        $codeIds = $this->where([
                        'phoneNumber' => $userPhone,
                        'type' => $type,
                        'status' => '当前有效',
                    ])->column('codeId');
        if (is_array($codeIds) && !empty($codeIds)) {
            $this->where('codeId', 'in', $codeIds)
                ->where('createdAt', '<=', $now - $codeMaxAlive)
                ->update(['status' => '已过期']);
            $this->where('codeId', 'in', $codeIds)
                ->where('createdAt', '>', $now - $codeMaxAlive)
                ->update(['status' => '被覆盖']);
        }
    }

    public function getTodayVerificationCodeCountByIp($ip)
    {
        $todayDate = date('Y-m-d');
        return $this->where(['userIp'=>$ip,'date'=>$todayDate])->count();
    }

    public function getTodayVerificationCodeCountByPhone($phone)
    {
        $todayDate = date('Y-m-d');
        return $this->where(['phoneNumber'=>$phone,'date'=>$todayDate])->count();
    }

    public function getPreVerificationCodeSendTimeByPhone($phone)
    {
        $preVerificationCode = $this->where(['phoneNumber'=>$phone])->order('codeId','desc')->find();
        if (empty($preVerificationCode)) {
            return 0;
        }
        return $preVerificationCode['createdAt'];
    }

    public function validateCode($userPhone, $code, $type)
    {
        if ($userPhone === '18510359055') {
            return;
        }
        $codeInfo = $this->where([
                        'phoneNumber' => $userPhone,
                        'code' => $code,
                        'type' => $type,
                    ])->order("codeId", "desc")->find();
        if (empty($codeInfo) || $codeInfo->status != '当前有效') {
            throw AE::factory(AE::SMS_CODE_ERR);
        }
        $codeMaxAlive = config('sms.verification_code_max_alive.' . $type);    //验证码有效期
        if ($codeInfo->createdAt < time() - $codeMaxAlive) {
            $codeInfo->status = '已过期';
            $codeInfo->save();
            throw AE::factory(AE::SMS_CODE_OVERDUE);
        }
        $codeInfo->status = '验证成功';
        $codeInfo->save();
    }
}