<?php

namespace Core;

use \Yaf\Registry;

/**
 * ---------------------------------------
 * 服务-调用核心类
 * ---------------------------------------
 * @author   Caiwh <471113744@qq.com>
 * @version  2017-01-11
 * ---------------------------------------
 */
class PublicsServ
{
  public static function getDB($node)
  {
    $res = new Resources(Registry::get('config'));
    return $res->getDB($node);
  }

  public static function getRedis($node)
  {
    $res = new Resources(Registry::get('config'));
    return $res->getRedis($node);
  }
}