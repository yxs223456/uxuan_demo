<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/6/27
 * Time: 11:19
 */
if (getAppEnvironment() === 'production') {
    return [
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 30,
        'password' => '',
    ];
} else {
    return [
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 30,
        'password' => '',
    ];
}