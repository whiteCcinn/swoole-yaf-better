<?php
try
{
  define('DS', DIRECTORY_SEPARATOR);
  define('APPLICATION_NAME', 'mysql_proxy');
  define('APPLICATION_PATH', realpath(__DIR__));
  include(sprintf('%s/service/%s_server.php', APPLICATION_PATH, APPLICATION_NAME));

  $server = new mysql_proxy_server('mp');
  $server->run();
} catch (Exception $e)
{
  die('run-ERROR: ' . $e->getMessage() . PHP_EOL);
}