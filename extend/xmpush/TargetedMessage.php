<?php
/**
 * 要发送的消息内容和消息的发送目标.
 * @author wangkuiwei
 * @name TargetedMessage
 * @desc 构建要发送的消息内容和消息的发送目标。
 *
 */
namespace xmpush;

class TargetedMessage {
    const TARGET_TYPE_REGID = 1;
    const TARGET_TYPE_ALIAS = 2;
    const TARGET_TYPE_USER_ACCOUNT = 3;
    private $targetType;
    private $target;
    /**
     * @var Message
     */
    private $message;

    public function __construct() {

    }

    /*
     * targetType表示目标类型，目前支持TargetedMessage.TARGET_TYPE_REGID和TargetedMessage.TARGET_TYPE_ALIAS两种类型。
     * 当targetType是TARGET_TYPE_REGID时，target需要设置为regid；当targetType是TARGET_TYPE_ALIAS时，target需要设置为alias。
     */
    public function setTarget($target, $targetType) {
        $this->targetType = $targetType;
        $this->target = $target;
    }

    public function setMessage(Message $message) {
        $this->message = $message;
    }

    public function getFields() {
        return array(
            'target' => $this->target,
            'message' => $this->message->getJSONInfos()
        );
    }
}

?>
