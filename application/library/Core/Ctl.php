<?php
namespace Core;

/**
 * ---------------------------------------
 * 控制器基类-二层基类
 * ---------------------------------------
 * @author   Caiwh <471113744@qq.com>
 * @version  2017-01-11
 * ---------------------------------------
 */
abstract class Ctl extends BaseCtl
{
  final protected function _init_()
  {
    $this->__init__();
  }

  // 子类如果想初始化加载就覆盖这个方法
  protected function __init__(){}
}