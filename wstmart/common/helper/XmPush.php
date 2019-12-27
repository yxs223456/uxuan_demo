<?php
/**
 * Created by PhpStorm.
 * User: chenxj
 * Date: 2018/10/10
 * Time: 18:42
 */

namespace wstmart\common\helper;

use xmpush\Builder;
use xmpush\HttpBase;
use xmpush\Sender;
use xmpush\Constants;
use xmpush\Stats;
use xmpush\Tracer;
use xmpush\Feedback;
use xmpush\DevTools;
use xmpush\Subscription;
use xmpush\TargetedMessage;
use xmpush\IOSBuilder;

class XmPush
{
    protected $andorid_secret;
    protected $andorid_package;

    protected $ios_secret;
    protected $ios_bundleId;

    public function __construct()
    {
        $this->andorid_secret = config('xmpush.android.appSecret');
        $this->andorid_package = config('xmpush.android.package');

        $this->ios_secret = config('xmpush.ios.appSecret');
        $this->ios_bundleId = config('xmpush.ios.bundleId');
    }

    public function pushRegidToAndroid($passThrough=1,$title='', $payload='', $extra=[], $regId=null, $intentUri=null, $webUri=null, $desc='')
    {
        // 常量设置必须在new Sender()方法之前调用
        Constants::setPackage($this->andorid_package);
        Constants::setSecret($this->andorid_secret);
        $sender = new Sender();
        // message1 演示自定义的点击行为
        $message = new Builder();
        if ($passThrough!=1) {
            $message->title($title);  // 通知栏的title
            $message->description($desc); // 通知栏的descption
        }
        $message->passThrough($passThrough);  // 这是一条通知栏消息，如果需要透传，把这个参数设置成1,同时去掉title和descption两个参数
        $message->payload($payload); // 携带的数据，点击后将会通过客户端的receiver中的onReceiveMessage方法传入。
        $message->extra(Builder::notifyForeground, 1); // 应用在前台是否展示通知，如果不希望应用在前台时候弹出通知，则设置这个参数为0
        if (!empty($intentUri)) {
            $message->extra(Builder::notifyEffect, 2);
            $message->extra(Builder::intentUri, $intentUri);
        }
        if (!empty($webUri)) {
            $message->extra(Builder::notifyEffect, 3);
            $message->extra(Builder::webUri, $webUri);
        }
        if (!empty($extra)) {
            foreach ($extra as $key=>$item) {
                $message->extra($key, $item);
            }
        }
        $message->notifyId(4); // 通知类型。最多支持0-4 5个取值范围，同样的类型的通知会互相覆盖，不同类型可以在通知栏并存
        $message->build();
        $targetMessage = new TargetedMessage();
        $targetMessage->setTarget('regid', TargetedMessage::TARGET_TYPE_REGID); // 设置发送目标。可通过regID,alias和topic三种方式发送
        $targetMessage->setMessage($message);
        if ($regId) {
            return $sender->send($message, $regId)->getRaw();
        } else {
            return $sender->broadcastAll($message)->getRaw();
        }

    }

    public function pushRegidToIos($title, $payload, $extra=[],$desc='', $regId=null)
    {
        Constants::setBundleId( $this->ios_bundleId);
        Constants::setSecret($this->ios_secret);
        $message = new IOSBuilder();
        $message->title($title);
        $message->description($desc);
        $message->body($payload);
        $message->soundUrl('default');
        $message->badge('4');
        if (!empty($extra)) {
            foreach ($extra as $key=>$item) {
                $message->extra($key, $item);
            }
        }
        $message->build();
        $sender = new Sender();
        if ($regId) {
            return $sender->send($message, $regId)->getRaw();
        } else {
            return $sender->broadcastAll($message)->getRaw();
        }
    }
}