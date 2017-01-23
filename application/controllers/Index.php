<?php

use Core\Ctl;

class IndexController extends Ctl
{

  public function __init__()
  {
    echo '初始化控制器';
  }

  /**
   * 默认动作
   * Yaf支持直接把Yaf_Request_Abstract::getParam()得到的同名参数作为Action的形参
   * 对于如下的例子, 当访问http://yourhost/Test/index/index/index/name/root 的时候, 你就会发现不同
   */
  public function indexAction($name = "Stranger")
  {
    $ret = (new TestModel())->getRows();
    var_dump($ret);

    return true;
  }
}
