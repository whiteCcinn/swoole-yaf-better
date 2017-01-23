<?php
define("DS", '/');
define('SYS_START_TIME', microtime(true));          // 启始时间
define('SYS_MEMORY_USE', memory_get_usage());       // 启始内存
define('APPLICATION_PATH', dirname(__FILE__) . DS . '..');//指向public上一级的目录 ../
define('CRLF', (PHP_SAPI === 'cli' ? PHP_EOL : '<br />' . PHP_EOL));

$application = new \Yaf\Application(APPLICATION_PATH . "/conf/application.ini");

$application->bootstrap()->run();
?>
