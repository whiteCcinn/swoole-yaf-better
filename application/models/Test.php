<?php

use \Core\BaseMdl;

Class TestModel extends BaseMdl
{

  public function __construct()
  {
    $this->init();
  }

  public function getRows()
  {
    $sql = 'select * from `yaf_user`';
    $rs  = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return $rs;
  }
}