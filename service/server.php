<?php

class HttpServer
{
  public static $instance;

  public        $http;
  public static $get;
  public static $post;
  public static $header;
  public static $server;
  public static $module;
  public static $controller;
  public static $action;
  private       $application       = null;

  private $config = array(
    'worker_num'    => 10,
    'daemonize'     => false,
    'max_request'   => 10000,
    'dispatch_mode' => 3, // 抢占模式
    'log_file'      => '/wwwroot/share/swoole/yaf/log/server.log',
  );

  /**
   * HttpServer constructor.
   */
  public function __construct()
  {
    $this->http = new swoole_http_server("0.0.0.0", 9501);
    $this->http->set($this->config);
    $this->bind($this->config);

    $this->watchServerLog($this->http);
  }

  /**
   *  创建子进程进行监听server.log日志文件
   * @return bool
   */
  private function watchServerLog(\Swoole\Server $serv)
  {

    $process = new \Swoole\Process(function (\Swoole\Process $process) use ($serv)
    {
      //创建一个inotify句柄
      $fd = inotify_init();

      //监听文件，仅监听修改操作，如果想要监听所有事件可以使用,IN_ALL_EVENTS，IN_MODIFY | IN_CREATE | IN_DELETE
      $watch_descriptor = inotify_add_watch($fd, dirname(__DIR__) . '/log/server.log', IN_MODIFY | IN_ACCESS);

      // 加入到swoole的异步epoll事件循环中
      \Swoole\Event::add($fd, function ($fd) use ($serv)
      {
        $events = inotify_read($fd);
        if ($events)
        {
          foreach ($events as $event)
          {
            //TODO 写入Redis日志，或者先放在异步队列中去，在异步队列中写入Redis日志
            echo "inotify Event :" . var_export($event, true) . "\n";
          }
        }
      });

    });
    $process->name('php_inotify_process:manager');
    $serv->addProcess($process);

//    return true;
  }

  /**
   * 获取Http_Server对象
   * @return HttpServer
   */
  public static function getInstance()
  {
    if (!self::$instance)
    {
      self::$instance = new HttpServer;
    }
    return self::$instance;
  }

  /**
   * 启动http代理服务
   */
  public function run()
  {
    $this->http->start();
  }

  public function onRequest($request, $response)
  {
    $response->gzip(1);

    //注册全局信息
    $this->initRequestParam($request);
    \Yaf\Registry::set('SWOOLE_HTTP_REQUEST', $request);
    \Yaf\Registry::set('SWOOLE_HTTP_RESPONSE', $response);

    ob_start();
    try
    {
      $yaf_request = new \Yaf\Request\Http($request->server['request_uri']);

      $this->application->bootstrap()->getDispatcher()->dispatch($yaf_request);

      // unset(\Yaf\Application::app());
    } catch (\Yaf\Exception $e)
    {
      var_dump($e, 'error');
    }

    $result = ob_get_contents();

    ob_end_clean();

    if ($this->http->connection_info($response->fd) !== false)
    {
      /**
       * 如果没有response成功的话 , 通知监听服务器reload框架主进程
       */
      if (!$response->end($result))
      {
        $this->http->reload();
      }
    }
  }

  /**
   * Method  initRequestParam
   * @desc   将请求信息放入全局注册器中
   * @author WenJun <wenjun01@baidu.com>
   * @param swoole_http_request $request
   * @return bool
   */
  private function initRequestParam(swoole_http_request $request)
  {
    //将请求的一些环境参数放入全局变量桶中
    $server = isset($request->server) ? $request->server : array();
    $header = isset($request->header) ? $request->header : array();
    $get    = isset($request->get) ? $request->get : array();
    $post   = isset($request->post) ? $request->post : array();
    $cookie = isset($request->cookie) ? $request->cookie : array();
    $files  = isset($request->files) ? $request->files : array();

    \Yaf\Registry::set('REQUEST_SERVER', $server);
    \Yaf\Registry::set('REQUEST_HEADER', $header);
    \Yaf\Registry::set('REQUEST_GET', $get);
    \Yaf\Registry::set('REQUEST_POST', $post);
    \Yaf\Registry::set('REQUEST_COOKIE', $cookie);
    \Yaf\Registry::set('REQUEST_FILES', $files);
    \Yaf\Registry::set('REQUEST_RAW_CONTENT', $request->rawContent());

    return true;
  }

  // 绑定回调函数
  private function bind($config)
  {
    $this->http->on('Start', [$this, 'onStart']);
    $this->http->on('Close', [$this, 'onClose']);
    $this->http->on('Connect', [$this, 'onConnect']);
    $this->http->on('Request', [$this, 'onRequest']);
    $this->http->on('Receive', [$this, 'onReceive']);
    $this->http->on('Shutdown', [$this, 'onShutdown']);
    $this->http->on('WorkerStop', [$this, 'onWorkerStop']);
    $this->http->on('WorkerStart', [$this, 'onWorkerStart']);
    $this->http->on('WorkerError', [$this, 'onWorkerError']);
    $this->http->on('ManagerStop', [$this, 'onManagerStop']);
    $this->http->on('ManagerStart', [$this, 'onManagerStart']);
    if (isset($config['task_worker_num']) && boolval($config['task_worker_num']))
    {
      $this->http->on('Task', [$this, 'onTask']);
      $this->http->on('Finish', [$this, 'onFinish']);
    }

    return true;
  }

  /**
   * start()回调函数
   * @param \Swoole\Server $serv
   * @return bool
   */
  public function onStart(\Swoole\Server $serv)
  {
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    $msg  = "->[onStart] PHP=" . PHP_VERSION . " yaf=" . \YAF\VERSION . " swoole=" . SWOOLE_VERSION . " Master-Pid={$this->http->master_pid} Manager-Pid={$this->http->manager_pid}" . ' time=' . $time . PHP_EOL;
    $this->_write_file(dirname(__DIR__) . '/log/site_proxy.log', $msg);
    swoole_set_process_name("php_site_proxy_server:master");

    return true;
  }

  /**
   * 管理进程启动
   * @param \Swoole\Server $serv
   */
  public function onManagerStart(\Swoole\Server $serv)
  {
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    $msg  = "->[onManagerStart] time=" . $time . PHP_EOL;
    $this->_write_file(dirname(__DIR__) . '/log/site_proxy.log', $msg);
    swoole_set_process_name("php_site_proxy_server:manager");
  }

  /**
   * 启动Worker/Tasker进程
   * @param \Swoole\Server $serv
   * @param int $work_id
   */
  public function onWorkerStart(\Swoole\Server $serv, int $work_id)
  {
    // yaf框架启动
    define('APPLICATION_PATH', dirname(__DIR__));
    if ($this->application == null)
    {
      $this->application = new \Yaf\Application(APPLICATION_PATH .
        "/conf/application.ini");
    }

    // 进程命名定义
    if ($serv->taskworker)
    {
      $type = 'tasker';
    }
    else
    {
      $type = 'worker';
    }

    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    $msg  = "->[onWorkStart] {$type}_id = " . $work_id . ' time=' . $time . PHP_EOL;
    $this->_write_file(dirname(__DIR__) . '/log/site_proxy.log', $msg);
    swoole_set_process_name("php_site_proxy_server:{$type}:worker");
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
    echo 'ok' . PHP_EOL;
    // TODO do something
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
    $msg  = "->[onConnect] fd :" . $fd . ",from_id : " . $from_id . ' time=' . $time . PHP_EOL;
    $this->_write_file(dirname(__DIR__) . '/log/site_proxy.log', $msg);
  }

  /**
   * TCP客户端连接关闭后(页面)，在worker进程中回调此函数
   * @param \Swoole\Server $serv
   */
  public function onClose(\Swoole\Server $serv, int $work_id)
  {
    // 回收对应进程申请的资源
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    $msg  = "->[onClose] time=" . $time . PHP_EOL;
    $this->_write_file(dirname(__DIR__) . '/log/site_proxy.log', $msg);
  }

  /**
   * worker/tasker进程结束的时候调用
   * 在此函数中可以回收worker进程申请的各类资源
   * @param \Swoole\Server $serv
   * @param int $work_id
   * @return bool
   */
  public function onWorkerStop(\Swoole\Server $serv, int $work_id)
  {
    // 回收对应进程申请的资源
    if (is_resource($this->application))
    {
      $this->application = null;
    }
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    $msg  = "->[onWorkerStop] work_id" . $work_id . ' time=' . $time . PHP_EOL;
    $this->_write_file(dirname(__DIR__) . '/log/site_proxy.log', $msg);

    return true;
  }

  /**
   * manager进程结束的时候调用
   * @param \Swoole\Server $serv
   * @return bool
   */
  public function onManagerStop(\Swoole\Server $serv)
  {
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    $msg  = "->[onManagerStop] time=" . $time . PHP_EOL;
    $this->_write_file(dirname(__DIR__) . '/log/site_proxy.log', $msg);

    return true;
  }

  /**
   * master进程结束
   * 强制kill进程不会回调onShutdown，如kill -9
   * 需要使用kill -15来发送SIGTREM信号到主进程才能按照正常的流程终止
   * @return bool
   */
  public function onShutdown(\Swoole\Server $serv)
  {
    // 移除监听器文件描述符
//    \Swoole\Event::del($this->_notify_handler);
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    $msg  = "->[onShutdown] time=" . $time . PHP_EOL;
    $this->_write_file(dirname(__DIR__) . '/log/site_proxy.log', $msg);
    return true;
  }

  /**
   * 当worker/tasker进程发生异常后会在Manager进程内回调此函数。
   * 此函数主要用于报警和监控，一旦发现Worker进程异常退出，那么很有可能是遇到了致命错误或者进程CoreDump.
   * @param \Swoole\Server $serv
   * @return bool
   */
  public function onWorkerError(\Swoole\Server $serv, int $work_id, int $work_pid, int $exit_code)
  {
    $time = gmdate("Y-m-d H:i:s", time() + 8 * 60 * 60);
    $msg  = "->[onWorkerError] work_id=" . $work_id . ",work_pid=" . $work_pid . ",exit_code=" . $exit_code . ' time=' . $time . PHP_EOL;
    $this->_write_file(dirname(__DIR__) . '/log/site_proxy.log', $msg);

    return true;
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
    // TODO 异步任务
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
    $msg  = "->[onFinish] task_id={$task_id},data={$data}" . ' time=' . $time . PHP_EOL;
    $this->_write_file(dirname(__DIR__) . '/log/site_proxy.log', $msg);
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
}

$server_obj = HttpServer::getInstance();
$server_obj->run();
