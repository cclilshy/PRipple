<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-22 01:15:45
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Dispatch\Standard;

use Exception;
use Cclilshy\PRipple\Dispatch\Build;
use Cclilshy\PRipple\Dispatch\Lister;
use Cclilshy\PRipple\Dispatch\Dispatcher;
use Cclilshy\PRipple\Communication\Socket\SocketInet;
use Cclilshy\PRipple\Communication\Socket\SocketUnix;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;
use Cclilshy\PRipple\Communication\Standard\AisleInterface;
use Cclilshy\PRipple\Dispatch\EventTemplate\CommonTemplate;
use Cclilshy\PRipple\Communication\Socket\ServerSocketManager;

/**
 * 事件处理器开发依赖
 */
abstract class ServiceAbstract implements ServiceInterface
{
    protected ServerSocketManager $serverSocketManager;
    protected AisleInterface      $dispatcherServerAisle;
    protected mixed               $dispatcherServer;
    protected array               $socketTypeMap;

    public function start(): void
    {
        try {
            $this->serverSocketManager   = ServerSocketManager::createServer(SocketInet::class, '0.0.0.0', 2222, [SO_REUSEADDR => 1],);
            $this->dispatcherServer      = SocketUnix::connect(Dispatcher::UNIX_HANDLE);
            $this->dispatcherServerAisle = SocketAisle::create($this->dispatcherServer);
            $this->subscribe(array(
                'Http' => array(
                    'LOGIN'   => Lister::FORMAT_MESSAGE,
                    'DEFAULT' => Lister::FORMAT_BUILD,
                )
            ));
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
        $this->publish(new Build('Http', null, new CommonTemplate('LOGIN', ''), null));
        while (true) {
            $readList = array_merge([
                $this->serverSocketManager->getEntranceSocket(),
                $this->dispatcherServer
            ], $this->serverSocketManager->getClientSockets() ?? []);

            if (socket_select($readList, $_, $_, null)) {
                foreach ($readList as $readSocket) {
                    $socketName = ServerSocketManager::getNameBySocket($readSocket);
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

    protected function subscribe(array $config): void
    {
        $event = new CommonTemplate('E_SUBSCRIBE', $config);
        $build = new Build('Http', null, $event, null);
        Dispatcher::AGREE::send($this->dispatcherServerAisle, (string)$build);
    }

    protected function publish(Build $build): void
    {
        Dispatcher::AGREE::send($this->dispatcherServerAisle, (string)$build);
    }

    protected function handlerDispatcherMessage(): void
    {
        $messageType = -9;
        $context     = Dispatcher::AGREE::cutWithInt($this->dispatcherServerAisle, $messageType);

        switch ($messageType) {
            case Lister::FORMAT_MESSAGE:
                $this->execMessage($context);
                break;
            case Lister::FORMAT_EVENT:
                $package = Build::unSerialize($context);
                $this->execPackage($package);
                break;
            case Lister::FORMAT_BUILD:
                $event = unserialize($context);
                $this->execEvent($event);
                break;
        }
    }
}
