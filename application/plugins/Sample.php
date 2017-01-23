<?php
use \Yaf\Plugin_Abstract;

/**
 * @name SamplePlugin
 * @desc Yaf定义了如下的6个Hook,插件之间的执行顺序是先进先Call
 * @see http://www.php.net/manual/en/class.yaf-plugin-abstract.php
 * @author root
 */
class SamplePlugin extends Plugin_Abstract
{
  static $pre_obj = '';

  public function routerStartup(\Yaf\Request_Abstract $request, \Yaf\Response_Abstract $response)
  {
    if ($request === self::$pre_obj)
    {
      return false;
    }
    self::$pre_obj = $request;

    echo 123;
  }
}
