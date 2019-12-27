<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/5
 * Time: 16:59
 */
namespace wstmart\app\service;

class Users
{
    public static function getUserByCache()
    {
        return $GLOBALS['query'];
    }
}