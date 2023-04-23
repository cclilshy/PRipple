<?php
declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-22 01:15:45
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Service;

use Exception;
use Cclilshy\PRipple\Console;
use Cclilshy\PRipple\Dispatch\Dispatcher;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Communication\Socket\Manager;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;
use Cclilshy\PRipple\Communication\Socket\SocketUnix;

/**
 * 事件处理器开发依赖
 */
abstract class Service extends ServiceInfo implements ServiceStandard
{
    public const PS_START = 'PS_START';
    public const PS_CLOSE = 'PS_CLOSE';
    public const PC_CLOSE = 'PC_CLOSE';

    public Manager     $serverSocketManager;
    public SocketAisle $dispatcherServerAisle;
    public string      $publish;
    public mixed       $dispatcherServer;
    public array       $socketTypeMap;

    public string $socketType;
    public string $serverAddress;
    public int    $serverPort;
    public array  $socketOptions;
    public int    $selectBlockLine;
    public bool   $isServer = false;

    /**
     * 服务配置
     */
    public function __construct(string|null $name = null)
    {
        if ($name !== null) {
            $this->publish = $name;
        } else {
            $this->publish = get_class($this);
            $this->publish = str_replace('\\', '_', $this->publish);
        }
        parent::__construct($this->publish);
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
            if (!$this->reConnectDispatcher()) {
                throw new Exception("无法连接调度器,请确认调度器正常启动");
            }
            if ($this->isServer) {
                $this->serverSocketManager = Manager::createServer($this->socketType, $this->serverAddress, $this->serverPort, $this->socketOptions);
            }
        } catch (Exception $e) {
            Console::debug("[Service]", $e->getMessage());
            return;
        }

        while (true) {
            $readList = array($this->dispatcherServer);
            if ($this->isServer) {
                $readList = array_merge($readList, [$this->serverSocketManager->getEntranceSocket()], $this->serverSocketManager->getClientSockets() ?? []);
            }

            $this->selectBlockLine = __LINE__ + 1;
            if (socket_select($readList, $_, $_, 0, 1000000)) {
                foreach ($readList as $readSocket) {
                    if (!$this->isServer) {
                        $this->handlerDispatcherMessage();
                        continue;
                    }
                    $socketName = Manager::getNameBySocket($readSocket);
                    switch ($readSocket) {
                        case $this->serverSocketManager->getEntranceSocket():
                            // TODO::有新的客户端链接
                            $name                             = $this->serverSocketManager->accept($readSocket);
                            $this->socketTypeMap[$socketName] = 'client';
                            if ($client = $this->serverSocketManager->getClientByName($name)) {
                                $this->onConnect($client);
                            }
                            break;
                        case $this->dispatcherServer:
                            $this->handlerDispatcherMessage();
                            // TODO::调度器发来通知
                            break;
                        default:
                            // TODO::客户端消息
                            if ($client = $this->serverSocketManager->getClientBySocket($readSocket)) {
                                if ($client->read($context)) {
                                    if ($client->verify) {
                                        $this->onMessage($context, $client);
                                    } else {
                                        $this->handshake($context, $client);
                                    }
                                } else {
                                    $this->onClose($client);
                                    $this->serverSocketManager->removeClient($readSocket);
                                }
                            }
                    }
                }
            } else {
                if (isset($this->serverSocketManager)) {
                    $this->serverSocketManager->handleBufferContext();
                }

                if ($this->dispatcherServerAisle->getCacheLength() > 0) {
                    $this->dispatcherServerAisle->write("");
                }
                $this->heartbeat();
            }
        }
    }

    /**
     * 错误处理器
     *
     * @return void
     */
    private function registerErrorHandler(): void
    {
        return;
    }

    /**
     * @return bool
     */
    private function reConnectDispatcher(): bool
    {
        try {
            $this->dispatcherServer      = SocketUnix::connect(Dispatcher::$handleServiceUnixAddress);
            $this->dispatcherServerAisle = SocketAisle::create($this->dispatcherServer);
            $this->dispatcherServerAisle->setNoBlock();
            $this->noticeStart();
            return true;
        } catch (Exception $e) {
            Console::debug("[Subscribe]", $e->getMessage());
            return false;
        }
    }

    /**
     * 通知调度器启动
     *
     * @return void
     */
    public function noticeStart(): void
    {
        $this->publishEvent(Service::PS_START, null);
        $this->initialize();
    }

    /**
     * 发布一个事件
     *
     * @param string      $name    事件名称
     * @param mixed       $data    事件数据
     * @param string|null $message 携带消息
     * @return bool
     */
    public function publishEvent(string $name, mixed $data, string|null $message = null): bool
    {
        $event = new Event($this->publish, $name, $data);
        $build = new Build($this->publish, null, $event, $message);
        return $this->publish($build);
    }

    /**
     * 发布一个自定义的信息包
     *
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Build $package
     * @return bool
     */
    public function publish(Build $package): bool
    {
        return Dispatcher::AGREE::send($this->dispatcherServerAisle, (string)$package);
    }

    /**
     * 创建服务
     *
     * @param string     $socketType
     * @param string     $address
     * @param int|null   $port
     * @param array|null $options
     * @return void
     */
    public function createServer(string $socketType, string $address, int|null $port = 0, array|null $options = []): void
    {
        $this->socketType    = $socketType;
        $this->serverAddress = $address;
        $this->serverPort    = $port;
        $this->socketOptions = $options;
        $this->isServer      = true;
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
                Console::debug("[Server]", "Dispatcher is block,reconnect ...");
                sleep(1);
            } while (!$this->reConnectDispatcher());
            return;
        }
        switch ($messageType) {
            case Dispatcher::FORMAT_MESSAGE:
                $this->onMessage($context, $this->dispatcherServer);
                break;
            case Dispatcher::FORMAT_BUILD:
                $package = Build::unSerialize($context);
                $this->onPackage($package);
                break;
            case Dispatcher::FORMAT_EVENT:
                $event = unserialize($context);
                $this->builtEventHandle($event);
                $this->onEvent($event);
                break;
        }
    }

    /**
     * 内置事件处理
     *
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Event $event
     * @return bool
     */

    public function builtEventHandle(Event $event): bool
    {
        switch ($event->getName()) {
            case Service::PC_CLOSE:
                $this->dispatcherServerAisle->release();
                $this->noticeClose();
                exit;

            case Dispatcher::PE_DISPATCHER_CLOSE:
                exit;
            default:
                return false;
        }
    }

    /**
     * 通知调度器关闭
     *
     * @return bool
     */
    public function noticeClose(): bool
    {
        return $this->publishEvent(Service::PS_CLOSE, null);
    }

    /**
     * 声明订阅
     *
     * @param string     $publisher 订阅的发布者
     * @param string     $eventName 订阅的事件
     * @param int        $type      接收的消息类型
     * @param array|null $options
     * @return bool
     */
    public function subscribe(string $publisher, string $eventName, int $type, array|null $options = []): bool
    {
        $options = array_merge($options, [
            'publish' => $publisher,
            'event'   => $eventName,
            'type'    => $type,
        ]);
        return $this->publishEvent(Dispatcher::PD_SUBSCRIBE, $options);
    }

    /**
     * 取消订阅
     *
     * @param string      $publisher 取消的订阅者
     * @param string|null $eventName 取消的订阅事件
     * @return bool
     */
    public function unSubscribe(string $publisher, string|null $eventName = null): bool
    {
        return $this->publishEvent(Dispatcher::PD_SUBSCRIBE_UN, [
            'publish' => $publisher,
            'event'   => $eventName,
        ]);
    }
}
