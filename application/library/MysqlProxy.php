<?php

/**
 * ---------------------------------------
 * Mysql客户端
 * ---------------------------------------
 * [功能描述]
 * #class_info
 * ---------------------------------------
 * @author Caiwh <caiwh@adnonstop.com>
 * @date   2017/1/18
 * ---------------------------------------
 */
class MysqlProxy
{

  public $client = null;

  private $config = [
    'host'                  => '127.0.0.1',
    'port'                  => 9601,
    'timeout'               => 2,      //默认为0.1s，即100ms
    'package_max_length'    => 1024 * 640,  //协议最大长度 1M
    'open_length_check'     => true,
    'package_length_offset' => 0,      //第N个字节是包长度的值
    'package_body_offset'   => 4,      //第几个字节开始计算长度
    'package_length_type'   => 'N'      //协议类型
  ];

  public function __construct()
  {
    $this->client = new Swoole\Client(SWOOLE_SOCK_TCP);
    $this->client->set($this->config);
    if (!$this->client->connect($this->config['host'], $this->config['port'], $this->config['timeout']))
    {
      var_dump('connect fail');
    }
  }

  /**
   * 连接成功回调的函数
   * @param \Swoole\Client $client
   */
  public function onConnect(\Swoole\Client $client)
  {
    file_put_contents('client-onConnect.log', var_export($client, true), FILE_APPEND);
  }

  public function onReceive(\Swoole\Client $client, string $data)
  {

    file_put_contents('client-onReceive.log', var_export($data, true), FILE_APPEND);
  }

  public function bind()
  {
    $this->client->on('Receive', [$this, 'onReceive']);
  }

  public function run($msg)
  {
    $sql  = $msg;
    $data = msgpack_pack($sql);
    $this->client->send(pack('N', strlen($data)) . $data);

    $recv = @$this->client->recv();
    $len  = 0;
    if (!empty($recv))
    {
      extract(unpack('Nlen', $recv));
      $ret = substr($recv, -$len);
      $ret = msgpack_unpack($ret);
      $this->client->close();

      return $ret;
    }
    else
    {
      $this->client->close();

      return "fail";
    }

  }
}