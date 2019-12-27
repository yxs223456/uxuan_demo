<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/10/18
 * Time: 10:29
 */
namespace wstmart\common\helper;

include_once(\Env::get('root_path') . 'extend/aliyun-oss-php-sdk-master/autoload.php');
use OSS\OssClient;
use OSS\Core\OssException;

class AliyunOss
{
    protected static $accessKeyId = 'LTAI4FuqdGaJyBtpQBFbSNgW';
    protected static $accessKeySecret = 'o84dhoOWICkbfoEy6HsV9u3vz08KuP';
    protected static $endpoint = 'http://oss-cn-beijing.aliyuncs.com';
    protected static $bucket = 'images-kaios';

    /**
     * @param $filename string 文件名称
     * @param string $content The content object
     * @return mixed
     * @throws OssException
     */
    public static function putObject($filename, $content)
    {
        $accessKeyId = self::$accessKeyId;
        $accessKeySecret = self::$accessKeySecret;
        $endpoint = self::$endpoint;
        // 存储空间名称
        $bucket= self::$bucket;

        $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
        $result = $ossClient->putObject($bucket, $filename, $content);
        $rs = [
            'url' => $result['info']['url']
        ];
        return $rs;
    }
}