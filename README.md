#swoole-yaf-better

## 简介

PHP7.0.x+Swoole2.0.5+yaf3.0.5-dev+协程Mysql+协程Redis+inotify实时监控日志变化


----------

功能简介

 - 基于鸟哥的C语言高性能框架 `yaf` 框架。
 - 基于Swoole异步网络通信框架的 `HTTP_SERVER` ，结合nginx作为前端正向代理。
 - 基于Swoole的协程Coroutine `Mysql` 自定义Server，业务调用数据库的时候连接到协程Mysql减少DB的IO操作
 - 基于Swoole的协程Coroutine `Redis` 自定义Server，业务调用数据库的时候连接到协程Redis减少文件的IO操作 (待完善)
 - 基于linux底层C扩展 `inotify` 结合Swoole的异步事件epoll实现异步非阻塞监听日志文件，异常重启Server服务
 - 日志系统基于 `AMQP协议` 进行异步队列记录日志 (待完善)
 - 实现了基于 `yaf` 框架的基本封装，让控制器能更加灵活的控制入口
 - Mysql客户端和Mysql服务器端的协程服务采用自定义网络传输协议，让数据传输更加可靠
 - 采用shell脚本一键启动所有Server（目前暂未实现shell的编写，暂时采用分步启动）
 
----------

##安装

> 必要的扩展

 
 - nginx 1.7或以上
 - mysql 5.4或以上
 - PHP 7.0.x
 - extension=yaf.so
 - extension=swoole.so
 - extension=msgpack.so
 - extension=inotify.so

php.ini的配置

    [Swoole]
    swoole.use_namespace=On

    [yaf]
    ; you also choose develop test
    yaf.environ = product
    yaf.use_namespace = 1
    ; yaf.action_prefer = 0
    ; yaf.lowcase_path = 0
    ; yaf.library = NULL
    ; yaf.cache_config = 0
    ; yaf.name_suffix = 1
    ; yaf.name_separator = ""
    ; yaf.forward_limit = 5
    ; yaf.use_spl_autoload = 0

nginx.conf对应的配置
```nginx
server {
    listen       80;
    server_name  www.yourprojectname.com;

    #charset koi8-r;
    #access_log  /var/log/nginx/log/host.access.log  main;

    root   path/yaf/public;
    index  index.html index.htm index.php;

    if (!-e $request_filename) {
      rewrite ^/(.*)  /index.php?$1 last;
    }

    #error_page  404              /404.html;

    # redirect server error pages to the static page /50x.html
    #
    error_page   500 502 503 504  /50x.html;
    location = /50x.html {
        root   /usr/share/nginx/html;
    }

    # proxy the PHP scripts to Apache listening on 127.0.0.1:80
    #
    location ~ \.php$ {
             proxy_pass http://127.0.0.1:9501$request_uri;
             proxy_http_version 1.1;
             proxy_set_header Connection "keep-alive";
             proxy_set_header X-Real-IP $remote_addr;
             fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
             include fastcgi_params;
    }

    # pass the PHP scripts to FastCGI server listening on 127.0.0.1:9000
    #
    #location ~ \.php$ {
    #    root           path/yaf/public;
    #    fastcgi_pass   fastcgi_backend;
    #    fastcgi_index  index.php;
    #    fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
    #    include        fastcgi_params;
    #}

    # deny access to .htaccess files, if Apache's document root
    # concurs with nginx's one
    #
    #location ~ /\.ht {
    #    deny  all;
    #}

```                                                           


----------

##  浏览器访问

`http://www.yourprojectname.com/test/index/index`

注意：这里yaf采用的分模块设置，目前第一阶段只在test模块下测试


##Usage

cd project_root_path

`php run_server.php`

cd project_root_path/service

`php server.php`


----------
## 感谢

1.感谢Swoole : http://www.swoole.com

2.感谢鸟哥 : http://www.laruence.com/manual
