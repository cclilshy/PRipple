# PRipple
> 订阅发布模式下开发的纯事件驱动PHP高并发异步框架

## 运行环境

- OS Linux- PHP 8.2+
- PHP-Extension `posix` `pcntl` `sockets` `fileinfo`

## 安装

```bash
git clone https://github.com/cclilshy/PRipple
cd PRipple
composer install
```

## 运行

```bash
bin/pripple dth start
```

## 路由

```php
use Cclilshy\PRipple\Route\Route;

/**
 * @ params
 * @ string 路径
 * @ string/callback 控制器@方法名 / 函数体
 * @ string 附加参数名(与参数1的冒号取值对应)
 */
 
// 注册HTTP路由
Route::get('/', 'app\Http\controller\Index@index');
Route::get('/upload', 'app\Http\controller\Index@upload');
Route::post('/upload', 'app\Http\controller\Index@upload');

// 注册静态路由
// 注册静态目录
Route::static('/assets', Http::ROOT_PATH . '/public/assets/');  
Route::static('/robots.txt', Http::ROOT_PATH . '/public/robots.txt'); 
// 注册静态文件
Route::static('/favicon.ico', Http::ROOT_PATH . '/public/favicon.ico'); 

// 注册一个命令行应用,详见终端
Route::console("dth", '\Cclilshy\PRipple\Dispatch\Control');

// 注册一个服务,详见服务
Route::service("HttpService", '\Cclilshy\PRipple\Built\Http\Service');
```

## 命令行应用

> 例子 `\Cclilshy\PRipple\Dispatch\Control`

```php
<?php
namepsace \Cclilshy\PRipple\Dispatch;

class Control
{
    /**
     * 应用简介,用于Help查阅
     * @return string
     */
    public static function register(): string
    {
        return '应用简介,用于Help查阅';
    }

    /**
     * 应用入口
     * @param $argv @参数列表
     * @param $console  @控制台对象,提供基础的输出方法
     * @return void
     */
    public function main($argv, $console): void
    {
        
    }
}
```

## 服务开发

> 例子 `HttpService`

```php
namespace Cclilshy\PRipple\Built\Http;

use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;
use Cclilshy\PRipple\Service\Service as ServiceBase;
use Cclilshy\PRipple\Communication\Socket\Client;

class HttpService extends ServiceBase
{
    public function __construct()
    {
        // 定义服务名
        parent::__construct('HttpService');
        
        // 定义服务名后可以使用 $this->config('key') 访问对应 /config/{服务名}.php 的配置文件
        // 该类创建后会生成一个互斥的服务文件和一个唯一的pipe文件
        
        $this->pipe; //使用该服务的管道文件,详见管道
        
        // 储存指定服务信息
        $this->info(['pid'=>posix_getpid()]);
        
        // 获取服务信息
        $this->info();
        
        
        // 你可以在任何一个其他进程内通过以下方法获取服务信息
        if($service = ServiceBase::create('HttpService')->initLoad()){
            $info = $service->info();
        }else{
            //TODO: 服务没有被注册
        }
    }

    /**
     * 服务启动,开始监听前会运行
     * @return void
     */
    public function initialize(): void
    {
        // 创建一个监听
        $this->createServer(Communication::INET, $this->config('listen_address'), $this->config('listen_port'));
        if ($this->config('fiber')) {
            // 订阅一个事件 (被订阅的发布者,事件名称,接受类型)
            $this->subscribe('Timer', 'ControllerSleep', Dispatcher::FORMAT_EVENT);
        }
    }

    /**
     * 心跳,服务器空闲时会调用这些心跳以释放资源,非空闲时不会调用 
     * @return void
     */
    public function heartbeat(): void
    {
        foreach ($this->vestigial as $name) {
            unset($this->requests[$name]);
            unset($this->transfers[$name]);
        }
    }

    /**
     * 当你订阅了一个 `Dispatcher::FORMAT_EVENT` 类型的事件时, 该事件会触发这个方法
     * @param Event $event
     * @return void
     */
    public function onEvent(Event $event): void
    {
        $event->getName();  // 获取事件名称
        $event->getData(); // 获取事件数据
        $event->getPublisher(); // 获取发布者
        
        // 发布一个事件
        $this->publishEvent('事件名称','事件数据','消息');
    }

    /**
     * 当你订阅了一个 `Dispatcher::FORMAT_PACKAGE` 类型的事件时, 该事件会触发这个方法, 获得原始的消息包
     * @param Build $package
     * @return void
     */
    public function onPackage(Build $package): void
    {
        $package->getEvent(); // 获取事件(假如该包存有事件)
        $package->getPublisher(); // 获取发布者
        $package->getTargetHandlerName(); // 暂留待用
        $package->getUuid(); // 获取包的UUID
        $package->getMessage(); // 获取包携带的消息
        $package->signIn('http','签名数据'); // 为一个包签名
        
        // 发布一个包
        $this->publish($package);
    }

        
    // 以下方法处理
    // $this->createServer(Communication::INET, $this->config('listen_address'), $this->config('listen_port'));
    // 创建的服务的客户端交互

    /**
     * 一个新连接到达时触发
     * @param Client $client
     * @return void
     */
    public function onConnect(Client $client): void
    {
        $client->setNoBlock();
    }

    /**
     * 一个新连接尝试握手时触发
     * @param Client $client
     * @return bool|null
     */
    public function handshake(Client $client): bool|null
    {
        // 握手成功
        return $client->handshake();
    }

    /**
     * 当客户端连接数据到达时触发,这里的客户端指的是以下创建的客户端: 
     * @param string $context
     * @param Client $client
     * @return void
     * @throws \Throwable
     */
    public function onMessage(string $context, Client $client): void
    {
        // 将请求的报文加入缓存区并返回所有缓冲区的内容,用于处理客户端可能存在一次报文不完整的情况
        $text = $client->cache($context); 
    }

    /**
     * 当客户端关闭时触发
     * @param Client $client
     * @return void
     */
    public function onClose(Client $client): void
    {
        $this->vestigial[] = $client->getKeyName();
    }

    /**
     * 服务结束时触发,用于释放资源 
     * @return void
     */
    public function destroy(): void
    {

    }
}

```

## HTTP控制器

> Http服务支持Fiber运行模式

```php
<?php
namespace app\Http\controller;

use Cclilshy\PRipple\Built\Http\Request;
use Cclilshy\PRipple\Built\Http\Service;
use Cclilshy\PRipple\Built\Http\Controller;

class Index extends Controller
{
    // 所有控制器都必须成功继承到该类
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    public function index(): string
    {
        // 你可以在任何一个地方发布一个事件
        Service::$service->publishEvent('UserLogin',[
            'userId' => 1
        ]);
        
        // 你也可以在完全任何地方暂停而让出资源(不会堵塞进程)
        $this->sleep(10);
       
        // 设置各项数据
        $this->response->setHeader('key','value'); 
        $this->response->setCookie('cookie');
        $this->response->setContentType('type');
        
        $this->response->setCharset('utf8'); //默认utf8
        
        // 设置状态码,默认200
        $this->response->setStatusCode(200);
        
        // 实际上在Controller内,json会修改ContentType,并返回json文本
        return $this->json(['name'=>'cc']);
        // 返回要输出的文本,或者已实现 __toString 的类
        return 'hello';
        // Controller的__toString会返回该方法对应的模板
        return $this; 
    }

    public function upload(): string
    {
        // 流式上传(在上传到达之前处理请求)
        // 流式上传也是事件驱动的
        // $this->wait() 让出处理器,等待一个事件触发请求,返回该事件本身
        
        if ($this->request->isUpload) {
            $data = [];
            // 等待事件触发
            while ($event = $this->wait()) {
                switch ($event->getName()) {
                    case 'CompleteUploadFile':
                        //TODO: 当一个文件被上传完毕后
                        break;
                    case 'NewUploadFile':
                        //TODO: 当一个新的文件上传发生后
                        $data = $event->getData();
                        break;
                    case 'RequestComplete':
                        //TODO: 当该请求已经完成
                        return json_encode($data);
                }
            }
        } else {
            return $this;
        }
    }
}
```

## HTML模板

```php
<!-- 原生语句 -->
@php include __DIR__ . FS . 'header.html'; @endphp

<!-- 判断输出 -->
@if(\model\Member::isLogin())

<!-- 变量输出 -->
<p>name : {{$name}}</p>

<!-- 函数输出 -->
<p>{{ substr($describe,0,100); }}</p>

<!-- 循环输出,支持for/while/foreach -->
@foreach($arr as $key => $value)
<p>{{$key}} : {{$value}}</p>
@endforeach

<!--  判断尾  -->
@endif

<!-- 兼容Vue的写法 -->
这段文本不会被解析: @{{ message }}

<!-- 模板包含以`TEMPLATE_PATH为`根向下索引的模板文件-->
@embed("index/common") @endembed
```

## 管道

```php
use Cclilshy\PRipple\FileSystem\Pipe;

$pipe = \core\File\Pipe::create('name'); // 创建管道空间
$pipe = \core\File\Pipe::link('name'); // 连接管道
$pipe = $pipe->clone(); // 克隆一个管道(不共用流和指针,因此锁互斥,可以给子进程使用)
$wait = false;           // 是否堵塞
$pipe->lock($wait);      // 多个进程之间,对同一个名称管道的调用,只有一个进程能上锁成功
$pipe->unlock();        // 解锁
$pipe->close();         // 关闭管道
$pipe->release();       // 请确保该管道空间没人使用了
```

## 数据处理

```php
// 取文本区间
$pipe->section(0,0); //(开始,结束),第二个参数为空则自动追加到流末尾

// 读管道信息
$pipe->read();

// 尾部追加数据
$pipe->insert('hello');

// 指定位置开始覆写数据,如指定位置为0则清空文本
$pipe->write('test',1);

$pipe->eof; // 指针末尾,-1为空文本
$pipe->point; // 指针位置
```

## 日志

```php
use Cclilshy\PRipple\Log;
Log::insert("写入一段日志");
```

## 多进程

> 多进程也是机遇事件和进程树索引实现

```php
use Cclilshy\PRipple\Built\Process\Process;

$pid = Process::fork(function(){
    
});

// 你可以在任何一个进程里给进程发送消息,
// 进程树会找到对应的守护程序向它发送信号
Process::signal(SIGKILL,$pid);
```