<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/6/21
 * Time: 11:22
 */
/**
 * 获取客户端ip
 */
function getClientIp() {
    if (getenv('HTTP_CLIENT_IP')) {
        $clientIp = getenv('HTTP_CLIENT_IP');
    } else if (getenv('HTTP_X_FORWARDED_FOR')) {
        $clientIp = getenv('HTTP_X_FORWARDED_FOR');
    } else if (getenv('REMOTE_ADDR')) {
        $clientIp = getenv('REMOTE_ADDR');
    } else {
        $clientIp = '';
    }
    return $clientIp;
}

function preg_domain(){
    $domain = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allow_origin = [
        '.365uxuan.com',
        '.dev.xinker.me'
    ];
    foreach ($allow_origin as $k => $v) {
        if (strpos($domain, $v)) {
            header("Access-Control-Allow-Origin:{$domain}");
            header('Access-Control-Allow-Credentials: true');
            break;
        }
    }
    header("Access-Control-Allow-Headers:token");
}

/**
 * @param $url                      string 请求地址
 * @param $method                   string 请求类型
 * @param $postData                 null|json|array 请求的post数据
 * @param $isPostDataJsonEncode     bool post数据是否需要为json格式
 * @param $cookie                   null|string cookie数据
 * @param $isResponseJson           bool 请求$url的响应是否为json
 * @param header                    null|array 请求的消息报头
 * @param $isReturnHeader           bool 是否返回响应头信息
 * @return mixed                    请求的响应数据，失败时返回false
 */
function curl($url, $method = 'get', $postData = null, $isPostDataJsonEncode = false, $isResponseJson = false, $cookie = null, $header = null, $isReturnHeader = false) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if (stripos($url, 'https') !== false) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
    if ($isReturnHeader) {
        curl_setopt($ch, CURLOPT_HEADER, 1);
    }
    if (strtolower($method) == 'post') {
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($isPostDataJsonEncode && is_array($postData)) {
            $postData = json_encode($postData, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
    if (!empty($cookie)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    if (!empty($header) && is_array($header) && count($header)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    }
    $data = curl_exec($ch);
    $status = curl_getinfo($ch);
    $err = curl_error($ch);
    if ($err) {
        return false;
    }
    if (intval($status['http_code']) != 200) {
        return false;
    }
    if ($isResponseJson) {
        $data = json_decode($data, true);
    }
    return $data;
}

/**
 * 生成随机字符串
 */
function getRandomString($length = 32, $isNumeric = false) {
    if ($isNumeric) {
        $chars = '0123456789';
    } else {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    }
    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
}

function downloadExcel($tableStr, $title = '数据.xls') {
    header("Content-Type:application/vnd.ms-excel");
    header("Content-Disposition:attachment;filename=" . $title);
    echo "<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>
<html xmlns='http://www.w3.org/1999/xhtml'>
    <head>
        <meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
        <title>" . $title . "</title>
    </head>
    <body>" . $tableStr . "</body>
</html>";
}

function getInput($key = '', $default = null, $filter = null) {
    if (stripos($key, 'global.') === false) {
        return input($key, $default, $filter);
    } else {
        $key = explode('.', $key);
        if (isset($key[1]) && isset($GLOBALS['input'][$key[1]])) {
            return $GLOBALS['input'][$key[1]];
        } else {
            return null;
        }
    }
}

/**
 * 检验参数是否在给定的几个值内
 * @param $param
 * @param array ...$ranges
 * @param bool ...$strict
 * @return bool
 */
function checkIsIn($param, $strict = true, ...$ranges) {
    if ($strict) {
        if (in_array($param, $ranges, true)) {
            return true;
        }
    } else {
        if (in_array($param, $ranges)) {
            return true;
        }
    }
    return false;
}

/**
 * 验证是否为整数，默认验证 >=0 的整数
 * @param mixed $param 要验证的参数
 * @param bool $zero 是否准许为0
 * @param bool $positive 是否不小于0
 * @return bool
 */
function checkInt($param, $zero = true, $positive = true) {
    if (!preg_match('#^-?\d+$#', $param)) {
        return false;
    }
    if (!$zero && $param == 0) {
        return false;
    }
    if ($positive && $param < 0) {
        return false;
    }
    return true;
}

/*
     * 生成随机数
     */
function getRand( $length = 32 ) {
    $str='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $len=strlen($str)-1;
    $randstr='';
    for($i=0;$i<$length;$i++){
        $num=mt_rand(0,$len);
        $randstr .= $str[$num];
    }
    return $randstr;
}

/**
 * 上传图片
 * 判断图片来源：fromType 0：商家/用户   1：平台管理员
 */
function shopUploadPic($fromType=0) {
    $fileKey = key($_FILES);
    $dir = 'web';

    // 上传文件
    $file = request()->file($fileKey);
    if($file===null){
        throw new \wstmart\common\exception\AppException('上传文件不存在或超过服务器限制', -1);
    }
    $rule = [
        'type'=>'image/png,image/gif,image/jpeg,image/x-ms-bmp,video/mp4',
        'ext'=>'jpg,jpeg,gif,png,bmp,mp4',
        'size'=>'2097152'
    ];
    $info = $file->validate($rule)->rule('uniqid')->move(Env::get('root_path').'upload/'.$dir."/".date('Y-m-d'));
    if($info){
        $filePath = $info->getPathname();
        $filePath = str_replace(Env::get('root_path'),'',$filePath);
        $filePath = str_replace('\\','/',$filePath);
        $name = $info->getFilename();
        $filePath = str_replace($name,'',$filePath);
        //原图路径
        $imageSrc = trim($filePath.$name,'/');
        //图片记录
        WSTRecordImages($imageSrc, (int)$fromType);
        return $filePath.$name;
    }else{
        //上传失败获取错误信息
        throw new \wstmart\common\exception\AppException($file->getError(), -1);
    }
}

function pr($dtaa) {
    echo "<pre>";
    print_r($dtaa);
    echo "</pre>";
}

function setCacheFile($data) {
    $filename = './token.txt';
    $file = fopen($filename, 'w+');
    fwrite($file, $data);
    fclose($file);
}

function hideUserPhone($phone) {
    if (preg_match('#^\d{11}$#', $phone)) {
        return substr($phone, 0, 3) . '****' . substr($phone, 7);
    }
    return $phone;
}

function getUxuanAccessToken($appId) {
    $url = '47.94.80.136:8082/GetToken/getWechatToken?appid=' . $appId;
    $access_token = curl($url);
    return $access_token;
}

function transformTime($timestamp)
{
    $now = time();
    do {
        if ($now - $timestamp < 60) {
            $rs = '刚刚';
            break;
        }
        if ($now - $timestamp < 120) {
            $rs = '1分钟前';
            break;
        }
        if ($now - $timestamp < 180) {
            $rs = '2分钟前';
            break;
        }
        if ($now - $timestamp < 240) {
            $rs = '3分钟前';
            break;
        }
        $today = strtotime('today');
        if ($timestamp >= $today) {
            $rs = '今天 ' . date('H:i', $timestamp);
            break;
        }
        $yesterday = strtotime('yesterday');
        if ($timestamp >= $yesterday) {
            $rs = '昨天 ' . date('H:i', $timestamp);
            break;
        }
        $N = date('N', $now);
        $thisWeek = strtotime(date('Y-m-d', ($now - ($N - 1) * 86400)));
        if ($timestamp >= $thisWeek) {
            $n = $N == 1 ? '一' :
                ($N == 2 ? '二' :
                    ($N == 3 ? '三' :
                        ($N == 4 ? '四' :
                            ($N == 5 ? '五' :
                                ($N == 6 ? '六' : '日')))));
            $rs = '星期' . $n . ' ' . date('H:i', $timestamp);
            break;
        }
        $rs = date('Y年m月d日 H:i', $timestamp);
    } while (false);
    return $rs;
}

function fanliCurl(array $params, $url)
{
    $client_id = '15579425';
    $client_secret = 'tDHR7I55nbjrzMU0sLPxBLluoXsDUJvb';
    $params = array_merge($params, [
        'clientId' => $client_id,
        'timestamp' => time(),
    ]);
    ksort($params);
    $signStr = $client_secret;
    foreach ($params as $key=>$param) {
        $signStr .= $key . $param;
    }
    $signStr .= $client_secret;
    $sign = strtoupper(md5($signStr));
    $params['sign'] = $sign;
    $result = curl($url, 'post', $params);
    return $result;
}