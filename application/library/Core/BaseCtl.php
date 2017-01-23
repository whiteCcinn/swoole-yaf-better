<?php
namespace Core;

use \Yaf\Controller_Abstract;

/**
 * ---------------------------------------
 * 控制器基类-一层基类
 * ---------------------------------------
 * @author   Caiwh <471113744@qq.com>
 * @version  2017-01-11
 * ---------------------------------------
 */
abstract class BaseCtl extends Controller_Abstract
{
  /**
   * module
   * @var string
   */
  protected $m = '';

  /**
   * controller
   * @var string
   */
  protected $c = '';

  /**
   * action
   * @var string
   */
  protected $a = '';

  /**
   * 请求对象
   * @var object
   */
  protected $req = null;

  /**
   * 请求参数
   * @var array
   */
  protected $raw = array();


  final public function init()
  {
    $this->_init_();

    $this->req = $this->getRequest();
    $this->m   = $this->req->getModuleName();
    $this->c   = $this->req->getControllerName();
    $this->a   = $this->req->getActionName();
    $this->raw = $this->req->getParams();
  }

  protected function _init_(){}
}