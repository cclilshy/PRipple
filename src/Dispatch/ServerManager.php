<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-23 10:36:06
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Dispatch;

use Cclilshy\PRipple\Communication\Socket\SocketUnix;
use Cclilshy\PRipple\Dispatch\EventTemplate\CommonTemplate;
use Cclilshy\PRipple\Communication\Socket\ServerSocketManager;

class ServerManager
{
    // 拦截事件类型
    const CORE_BLOCK = -1;
    // 连接事件类型
    const CORE_CONNECT = 1;
    // 关闭事件类型
    const CORE_CLOSE = -2;
    // 消息到达类型
    const CORE_MESSAGE = 1;

    // 服务名称
    private string $serverName;
    // 套接字地址
    private string $socketAddress;
    // 套接字类型
    private int $socketType;
    // 套接字端口
    private int|null $socketPort;
    // 服务套接字选项
    private array|null $socketOption;
    // 与调度器的连接
    private mixed $dispatcherSocket;
    // 与调度器的连接
    private SocketUnix $dispatcherAisle;
    // 客户端套接字控制器
    private ServerSocketManager $clientSocketManager;
    // 处理器套接字控制器
    private ServerSocketManager $handlerSocketManager;
    // 黑名单规则列表
    private array $blackList = array();
    private array $socketTypeMap;

    public function __construct(int $socketType, string $socketAddress, int|null $socketPort = 0, array|null $socketOption = [])
    {
        $this->serverName    = get_class($this);
        $this->socketType    = $socketType;
        $this->socketAddress = $socketAddress;
        $this->socketPort    = $socketPort;
        $this->socketOption  = $socketOption;
    }

    public function info(): array
    {
        return [
            'serverName'    => $this->serverName ?? null,
            'socketType'    => $this->socketType ?? null,
            'socketAddress' => $this->socketAddress ?? null,
            'socketPort'    => $this->socketPort ?? null,
        ];
    }

    public function listen(): int
    {

        switch ($pid = pcntl_fork()) {
            case 0:
                // 子进程
                $this->work();
                break;
            default:
                return $pid;
        }
        return $pid;
    }

    public function work(): void
    {
        $this->dispatcherSocket     = SocketUnix::connect(Dispatcher::UNIX_SERVER);
        $this->handlerSocketManager = ServerSocketManager::createServer(SocketUnix::class, $this->serverName);
        $this->clientSocketManager  = ServerSocketManager::createServer($this->socketType, $this->socketAddress, $this->socketPort, $this->socketOption);

        while (true) {
            $readList = array_merge([
                $this->clientSocketManager->getEntranceSocket(),
                $this->handlerSocketManager->getEntranceSocket(),
                $this->dispatcherSocket
            ], $this->clientSocketManager->getClientSockets(), $this->handlerSocketManager->getClientSockets());

            $writeList  = [];
            $exceptList = [];

            if (socket_select($readList, $writeList, $exceptList, null)) {
                foreach ($readList as $readSocket) {
                    $name = ServerSocketManager::getNameBySocket($readSocket);
                    switch ($readSocket) {
                        case $this->clientSocketManager->getEntranceSocket():   //TODO::客户端入口有消息
                            socket_recvfrom($readSocket, $buf, 1024, 0, $ipAddress, $port);
                            if ($from = $this->block($ipAddress)) {
                                //TODO::该IP被拦截
                                $build = new Build($this->serverName, null, new CommonTemplate(ServerManager::CORE_BLOCK, [
                                    'ip'   => $ipAddress,
                                    'from' => $from
                                ]), null,);
                                // 通知服务器拦截事件
                                Dispatcher::AGREE::send($this->dispatcherAisle, $build);
                                break;
                            } else {
                                $this->clientSocketManager->accept($readSocket);
                                $build = new Build($this->serverName, null, new CommonTemplate(ServerManager::CORE_BLOCK, $this->clientSocketManager->getClientByName($name)), null,);
                                // 通知服务器连接事件
                                Dispatcher::AGREE::send($this->dispatcherAisle, $build);
                                $this->socketTypeMap[$name] = 'client';
                            }
                            break;
                        case $this->handlerSocketManager->getEntranceSocket():   //TODO::处理器入口有消息
                            $this->handlerSocketManager->accept($readSocket);
                            $this->socketTypeMap[$name] = 'handle';
                            break;
                        case $this->dispatcherSocket:   //TODO::来自调度器的消息
                            break;
                        default: //TODO::来自客户端或处理器的消息
                            if (!isset($this->socketTypeMap[$name]))
                                break;
                            switch ($this->socketTypeMap[$name]) {
                                case 'client':
                                    return;
                                    break;
                                case 'handle':
                                    break;
                            }

                            break;
                    }
                }
            }
        }
    }

    public function block(string $ipAddress): bool
    {
        //TODO::处理拦截逻辑并通知调度器 IP 访问 Service 被 `规则` 拦截
        return false;
    }

}
