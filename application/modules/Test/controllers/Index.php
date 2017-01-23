<?php
use Core\Ctl;

/**
 * ---------------------------------------
 * #class_name
 * ---------------------------------------
 * [功能描述]
 * #class_info
 * ---------------------------------------
 * @author Caiwh <caiwh@adnonstop.com>
 * @date   2017/1/7
 * ---------------------------------------
 */
class IndexController extends Ctl
{
  public function indexAction($name = "Stranger")
  {

    $mysql_obj = new MysqlProxy();
    $ret = $mysql_obj->run('select * from yaf_user');
    var_dump($ret);

    return true;
  }
}
