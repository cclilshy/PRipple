<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-22 01:15:45
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */
namespace Cclilshy\PRipple\Service;

use Exception;
use Cclilshy\PRipple\Console;
use Cclilshy\PRipple\Dispatch\Build;
use Cclilshy\PRipple\Dispatch\Dispatcher;
use Cclilshy\PRipple\Communication\Socket\Manager;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;
use Cclilshy\PRipple\Communication\Socket\SocketUnix;
use Cclilshy\PRipple\Dispatch\EventTemplate\CommonTemplate;
use Cclilshy\PRipple\Communication\Standard\CommunicationInterface;

/**
 * 事件处理器开发依赖
 */
abstract class Service implements ServiceStandard
{
    const PS_START = 'PS_START';
    const PS_CLOSE = 'PS_CLOSE';

    protected Manager                $serverSocketManager;
    protected CommunicationInterface $dispatcherServerAisle;
    protected string                 $publish;
    protected mixed                  $dispatcherServer;
    protected array                  $socketTypeMap;

    protected string $socketType;
    protected string $serverAddress;
    protected int    $serverPort;
    protected array  $socketOptions;
    protected int    $selectBlockLine;

    /**
     * 服务配置
     *
     * @param string     $socketType 套接字类型
     * @param string     $address    套接字地址
     * @param int|null   $port       套接字端口
     * @param array|null $options    套接字选项
     */
    public function __construct(string $socketType, string $address, int|null $port = 0, array|null $options = [])
    {
        $this->publish       = get_class($this);
        $this->publish       = str_replace('\\', '_', $this->publish);
        $this->socketType    = $socketType;
        $this->serverAddress = $address;
        $this->serverPort    = $port;
        $this->socketOptions = $options;
    }

    /**
     * 启动服务
     *
     * @return void
     */
    public function launch(): void
    {
        $this->registerErrorHandler();
        try {
            $this->serverSocketManager = Manager::createServer($this->socketType, $this->serverAddress, $this->serverPort, $this->socketOptions);
            if (!$this->reConnectDispatcher()) {
                throw new Exception("无法连接调度器,请确认调度器正常启动");
            }
        } catch (Exception $e) {
            Console::debug($e->getMessage());
            return;
        }

        while (true) {
            $readList              = array_merge([
                $this->serverSocketManager->getEntranceSocket(),
                $this->dispatcherServer
            ], $this->serverSocketManager->getClientSockets() ?? []);
            $this->selectBlockLine = __LINE__ + 1;
            if (socket_select($readList, $_, $_, null)) {
                foreach ($readList as $readSocket) {
                    $socketName = Manager::getNameBySocket($readSocket);
                    switch ($readSocket) {
                        case $this->serverSocketManager->getEntranceSocket():
                            // TODO::有新的客户端链接
                            $this->serverSocketManager->accept($readSocket);
                            $this->socketTypeMap[$socketName] = 'client';
                            break;
                        case $this->dispatcherServer:
                            $this->handlerDispatcherMessage();
                            // TODO::调度器发来通知
                            break;
                        default:
                            if ($this->serverSocketManager->getClientBySocket($readSocket)->read($context)) {
                                $this->execOriginalContext($context);
                            }
                        // TODO::客户端消息
                    }
                }
            }
        }
    }

    /**
     * 声明订阅
     *
     * @param string $publisher 订阅的发布者
     * @param string $eventName 订阅的事件
     * @param int    $type      接收的消息类型
     * @return void
     */
    protected function subscribe(string $publisher, string $eventName, int $type): void
    {
        $this->publishEvent(Dispatcher::PD_SUBSCRIBE, [
            'publish' => $publisher,
            'event'   => $eventName,
            'type'    => $type
        ]);
    }


    /**
     * 取消订阅
     *
     * @param string      $publisher 取消的订阅者
     * @param string|null $eventName 取消的订阅事件
     * @return void
     */
    protected function unSubscribe(string $publisher, string|null $eventName = null): void
    {
        $this->publishEvent(Dispatcher::PD_SUBSCRIBE_UN, [
            'publish' => $publisher,
            'event'   => $eventName,
        ]);
    }


    /**
     * 发布一个自定义的信息包
     *
     * @param \Cclilshy\PRipple\Dispatch\Build $package
     * @return void
     */
    protected function publish(Build $package): void
    {
        Dispatcher::AGREE::send($this->dispatcherServerAisle, (string)$package);
    }

    /**
     * 发布一个事件
     *
     * @param string      $name    事件名称
     * @param mixed       $data    事件数据
     * @param string|null $message 携带消息
     * @return void
     */
    protected function publishEvent(string $name, mixed $data, string|null $message = null): void
    {
        $event = new CommonTemplate($this->publish, $name, $data);
        $build = new Build($this->publish, null, $event, $message);
        $this->publish($build);
    }

    /**
     * 处理调度器返回消息
     *
     * @return void
     */
    private function handlerDispatcherMessage(): void
    {
        $messageType = -9;
        try {
            $context = Dispatcher::AGREE::cutWithInt($this->dispatcherServerAisle, $messageType);
        } catch (Exception $exception) {
            do {
                sleep(1);
                Console::debug("[Server] Dispatcher close reconnect ...");
            } while (!$this->reConnectDispatcher());
            return;
        }
        switch ($messageType) {
            case Dispatcher::FORMAT_MESSAGE:
                $this->execMessage($context);
                break;
            case Dispatcher::FORMAT_BUILD:
                $package = Build::unSerialize($context);
                $this->execPackage($package);
                break;
            case Dispatcher::FORMAT_EVENT:
                $event = unserialize($context);
                $this->execEvent($event);
                break;
        }
    }

    /**
     * 通知调度器启动
     *
     * @return void
     */
    protected function noticeStart(): void
    {
        $this->publishEvent(Service::PS_START, null);
        $this->initialize();
    }

    /**
     * 通知调度器关闭
     *
     * @return void
     */
    protected function noticeClose(): void
    {
        $this->publishEvent(Service::PS_CLOSE, null);
        exit;
    }


    private function reConnectDispatcher(): bool
    {
        try {
            $this->dispatcherServer      = SocketUnix::connect(Dispatcher::UNIX_HANDLE);
            $this->dispatcherServerAisle = SocketAisle::create($this->dispatcherServer);
            $this->noticeStart();
            return true;
        } catch (Exception $e) {
            Console::debug($e->getMessage());
            return false;
        }
    }

    /**
     * 错误处理器
     *
     * @return void
     */
    private function registerErrorHandler(): void
    {
        set_exception_handler(function (mixed $error) {
            var_dump($error);
            if (property_exists($this, 'errorHandler')) {
                return $this->errorHandler($error);
            }
            return false;
        });

        $_ = set_error_handler(function ($errno, $errStr, $errFile, $errLine) {
            if ($errFile === __FILE__ && $errLine === $this->selectBlockLine) {
                Console::debug(PHP_EOL . 'Service [' . $this->publish . '] Close.');
                die;
            } else {
                Console::debug($errStr);
            }
            if (property_exists($this, 'errorHandler')) {
                return $this->errorHandler($errno, $errStr, $errFile, $errLine);
            }
            return false;
        });
    }
}
