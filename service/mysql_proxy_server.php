<?php

/**
 * ---------------------------------------
 * Mysql代理服务器，协程
 * ---------------------------------------
 * [注意:]
 * 1.Swoole扩展的安装，版本需要在2.x以上
 * ---------------------------------------
 * @author   Caiwh <471113744@qq.com>
 * @version  2017-01-18
 * ---------------------------------------
 */
class mysql_proxy_server
{
  public $host;

  public $port;

  public $sw;

  public $env;

  public $uname;

  public $packType; // 0=json, 1=msgpack

  public $packMaxLen;

  public $server_ip;


  private $_mysql_config = array(
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'user'     => 'root',
    'password' => '',
    'database' => 'yaf',
    'chatset'  => 'utf8mb4',
  );

  function __construct(string $uname = 'mysql_proxy', string $configFile = '/conf/service.ini', string $env = 'product')
  {
    $this->uname        = $uname;
    $this->env          = $env;
    $this->server_ip    = current(swoole_get_local_ip());
    $config             = $this->getConfig(APPLICATION_PATH . $configFile);
    $config['log_file'] = sprintf($config['log_file'], $uname);
    $this->host         = $config['host'];
    $this->port         = (int)$config['port'];
    $this->pool_size    = (int)$config['pool_size'];

    unset($config['host'], $config['port']);

    $config['package_max_length'] = intval($config['package_max_length']);
    $this->packMaxLen             = $config['package_max_length'];
    $this->sw                     = new \Swoole\Server($this->host, $this->port);
    $this->sw->set($config);
    $this->bind($config);
  }

  /**
   * 获取配置信息
   * @param $file
   * @param bool $isSelf
   * @return \Yaf\Config\Ini
   */
  public function getConfig($file, $isSelf = true)
  {
    $config = new \Yaf\Config\Ini($file, $this->env);
    if ($isSelf)
    {
      return $config->get($this->uname)->toArray();
    }
    return $config;
  }

  public function onStart(\Swoole\Server $serv)
  {
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    $this->_write_file(dirname(__DIR__) . DS . 'log/mysql_proxy.log', "->[onStart] PHP=" . PHP_VERSION . " swoole=" . SWOOLE_VERSION . " Master-Pid={$this->sw->master_pid} Manager-Pid={$this->sw->manager_pid} time={$time}" . PHP_EOL);
    swoole_set_process_name("php-{$this->uname}-server:master");
  }

  /**
   * worker 进程主要接受数据的时候回调的函数和 tasker区分
   * @param \Swoole\Server $serv
   * @param int $fd
   * @param int $from_id
   * @param string $data
   */
  public function onReceive(\Swoole\Server $serv, int $fd, int $from_id, string $data)
  {

    $len  = 0;
    $res  = unpack('Nlen', $data);
    $len  = (int)$res['len'];
    $data = substr($data, -$len);

    $sql = msgpack_unpack($data);


    if ($serv->connection_info($fd) && !empty($sql))
    {
      $mysql_client = new Swoole\Coroutine\MySQL();
      $flag         = $mysql_client->connect($this->_mysql_config);
      if (!$flag)
      {
        $data = 'Connect Exception Or query is Empty';
        $serv->send($fd, pack('N', strlen($data)) . $data);
      }
      $data = $mysql_client->query($sql);
      $data = msgpack_pack($data);
      if ($serv->connection_info($fd))
      {
        $serv->send($fd, pack('N', strlen($data)) . $data);
      }
      else
      {
        $data = 'Connect Exception';
        $serv->send($fd, pack('N', strlen($data)) . $data);
      }
    }

    $data = 'Done';
    $serv->send($fd, pack('N', strlen($data)) . $data);
  }

  /**
   * 在work进程中回调
   * $fd是连接的文件描述符，发送数据/关闭连接时需要此参数
   * $from_id来自那个Reactor线程
   * onConnect/onClose这2个回调发生在worker进程内，而不是主进程。
   * UDP协议下只有onReceive事件，没有onConnect/onClose事件
   * @param \Swoole\Server $serv
   * @param int $fd
   * @param int $from_id
   */
  public function onConnect(\Swoole\Server $serv, int $fd, int $from_id)
  {
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    $this->_write_file(dirname(__DIR__) . DS . 'log/mysql_proxy.log', "->[onConnect] fd :" . $fd . ",from_id : " . $from_id . ' time=' . $time . PHP_EOL);
  }

  /**
   * 工作进程启动（worker和tasker）
   * onWorkerStart/onStart是并发执行的，没有先后顺序
   * $worker_id是一个从0-$worker_num之间的数字，表示这个worker进程的ID
   * $worker_id和进程PID没有任何关系
   * 详见onWorkStart.log查看serv对象
   */
  public function onWorkerStart(\Swoole\Server $serv, int $work_id)
  {
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    if ($serv->taskworker)
    {
      $type = 'tasker';
    }
    else
    {
      $type = 'worker';
    }

    $this->_write_file(dirname(__DIR__) . DS . 'log/mysql_proxy.log', "->[onWorkStart] {$type}_id = " . $work_id . ' time=' . $time . PHP_EOL);

    swoole_set_process_name("php-{$this->uname}-server:{$type}");
  }

  /**
   * 管理进程启动
   */
  public function onManagerStart(\Swoole\Server $serv)
  {
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);

    $this->_write_file(dirname(__DIR__) . DS . 'log/mysql_proxy.log', "->[onManagerStart] time={$time}" . PHP_EOL);

    swoole_set_process_name("php-{$this->uname}-server:manager");
  }

  /**
   * master进程结束
   * 强制kill进程不会回调onShutdown，如kill -9
   * 需要使用kill -15来发送SIGTREM信号到主进程才能按照正常的流程终止
   */
  public function onShutdown(\Swoole\Server $serv)
  {
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    $this->_write_file(dirname(__DIR__) . DS . 'log/mysql_proxy.log', "->[onShutdown] time={$time}" . PHP_EOL);
  }

  /**
   * manager进程结束的时候调用
   * @param \Swoole\Server $serv
   */
  public function onManagerStop(\Swoole\Server $serv)
  {
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    $this->_write_file(dirname(__DIR__) . DS . 'log/mysql_proxy.log', "->[onManagerStop] time={$time}" . PHP_EOL);
  }

  /**
   * worker/tasker进程结束的时候调用
   * 在此函数中可以回收worker进程申请的各类资源
   * @param \Swoole\Server $serv
   */
  public function onWorkerStop(\Swoole\Server $serv, int $work_id)
  {
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    // 回收对应进程申请的资源
    $this->_write_file(dirname(__DIR__) . DS . 'log/mysql_proxy.log', "->[onWorkerStop] work_id" . $work_id . ' time=' . $time . PHP_EOL);
  }

  /**
   * TCP客户端连接关闭后，在worker进程中回调此函数
   * @param \Swoole\Server $serv
   */
  public function onClose(\Swoole\Server $serv, int $work_id)
  {
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    // 回收对应进程申请的资源
    $this->_write_file(dirname(__DIR__) . DS . 'log/mysql_proxy.log', "->[onClose] time={$time}" . PHP_EOL);
  }

  /**
   * 当worker/tasker进程发生异常后会在Manager进程内回调此函数。
   * 此函数主要用于报警和监控，一旦发现Worker进程异常退出，那么很有可能是遇到了致命错误或者进程CoreDump.
   * @param \Swoole\Server $serv
   */
  public function onWorkerError(\Swoole\Server $serv, int $work_id, int $work_pid, int $exit_code)
  {
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    $this->_write_file(dirname(__DIR__) . DS . 'log/mysql_proxy.log', "->[onWorkerError] work_id=" . $work_id . ",work_pid=" . $work_pid . ",exit_code=" . $exit_code . ' time=' . $time . PHP_EOL);
  }

  // 绑定回调函数
  public function bind($config)
  {
    $this->sw->on('Start', [$this, 'onStart']);
    $this->sw->on('Close', [$this, 'onClose']);
    $this->sw->on('Connect', [$this, 'onConnect']);
    $this->sw->on('Receive', [$this, 'onReceive']);
    $this->sw->on('Shutdown', [$this, 'onShutdown']);
    $this->sw->on('WorkerStop', [$this, 'onWorkerStop']);
    $this->sw->on('WorkerStart', [$this, 'onWorkerStart']);
    $this->sw->on('WorkerError', [$this, 'onWorkerError']);
    $this->sw->on('ManagerStop', [$this, 'onManagerStop']);
    $this->sw->on('ManagerStart', [$this, 'onManagerStart']);
    if (isset($config['task_worker_num']) && boolval($config['task_worker_num']))
    {
      $this->sw->on('Task', [$this, 'onTask']);
      $this->sw->on('Finish', [$this, 'onFinish']);
    }
  }


  /**
   * Tasker回调的函数
   * worker进程可以使用swoole_server_task函数向task_worker进程投递新的任务
   * 当前的Task进程在调用onTask回调函数时会将进程状态切换为忙碌，这时将不再接收新的Task，当onTask函数返回时会将进程状态切换为空闲然后继续接收新的Task。
   * @param \Swoole\Server $serv
   * @param int $task_id
   * @param int $src_worker_id
   * @param string $data
   */
  public function onTask(\Swoole\Server $serv, int $task_id, int $src_worker_id, string $data)
  {
    $this->_write_file('onTask.log', $data . ' [time:]' . gmdate('Y-m-d H:i:s', time() + 8 * 60 * 60) . PHP_EOL);
    $start_time = microtime(true);
    $sinfo      = implode('|', [$task_id, $serv->worker_id, $src_worker_id, $start_time, '%s']);
    $time       = microtime(true) - $start_time;
    $exe_time   = number_format($time, 4);
    $serv->finish(sprintf($sinfo, $exe_time));
  }

  /**
   * task进程的onTask事件中没有调用finish方法或者return结果。worker进程不会触发onFinish
   * @param \Swoole\Server $serv
   * @param int $task_id
   * @param string $data
   */
  public function onFinish(\Swoole\Server $serv, int $task_id, string $data)
  {
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    $this->_write_file(dirname(__DIR__) . DS . 'log/mysql_proxy.log', "->[onFinish] task_id={$task_id},data={$data} time={$time}" . PHP_EOL);
  }

  /**
   * 由于异步IO只能在worker进程使用，并且异步文件IO目前只是实现性质，所以还是采用了原生的PHP写法
   * @param string $filename 文件名
   * @param string $msg 消息
   * @param int $flag 操作类型
   * @return int
   */
  private function _write_file($filename, $msg, $flag = FILE_APPEND)
  {
    $ret = file_put_contents($filename, $msg, $flag);
    return $ret;
  }

  // 启动服务
  public function run()
  {
    $this->sw->start();
  }
}