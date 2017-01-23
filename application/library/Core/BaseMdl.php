<?php
namespace Core;

use Core\PublicsServ as pub;

/**
 * ---------------------------------------
 * Model层基类
 * ---------------------------------------
 * @author   Caiwh <471113744@qq.com>
 * @version  2017-01-11
 * ---------------------------------------
 */
class BaseMdl
{

  /**
   * medoo
   * @var
   */
  public $db;

  public function init()
  {
    $this->db = pub::getDB('mysql');
  }
}