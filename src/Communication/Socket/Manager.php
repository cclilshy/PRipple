<?php

namespace Cclilshy\PRipple\Communication\Socket;
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-22 15:36:03
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

class Manager
{
    private mixed $entranceSocket;
    private array $clientSockets;
    private array $clientInfos;
    private array $identityHashMap;

    public function __construct(mixed $entranceSocket)
    {
        $this->entranceSocket = $entranceSocket;
    }

    /**
     * 创建服务
     *
     * @throws \Exception
     */
    public static function createServer(string $type, string $address, int|null $port = 0, array|null $options = array()): Manager|false
    {
        switch ($type) {
            case SocketInet::class:
                $server = SocketInet::create($address, $port, SOCK_STREAM, $options);
                return new self($server);
            case SocketUnix::class:
                $server = SocketUnix::create($address);
                return new self($server);
            default:
                return false;
        }
    }

    /**
     * 获取入口套接字
     *
     * @return mixed
     */
    public function getEntranceSocket(): mixed
    {
        return $this->entranceSocket;
    }

    /**
     * 同意一个连接
     *
     * @param mixed $socket
     * @return string|false
     */
    public function accept(mixed $socket): string|false
    {
        if ($client = socket_accept($socket)) {
            return $this->addClient($client);
        }
        return false;
    }

    /**
     * 加入客户端
     *
     * @param mixed $clientSocket
     * @return string
     */
    public function addClient(mixed $clientSocket): string
    {
        $name                       = Manager::getNameBySocket($clientSocket);
        $this->clientSockets[$name] = $clientSocket;
        $this->clientInfos[$name]   = new Client($clientSocket);
        return $name;
    }

    /**
     * 获取客户端HASH
     *
     * @param mixed $socket
     * @return string
     */
    public static function getNameBySocket(mixed $socket): string
    {
        return spl_object_hash($socket);
    }

    /**
     * 设置套接字身份 当一个客户端被赋予身份后 将在ServerSocket对象中建立索引 可以快速查找
     *
     * @param string $name
     * @param string $identity
     * @return bool
     */
    public function setIdentityByName(string $name, string $identity): bool
    {
        if ($client = $this->getClientSocketByName($name)) {
            $client->setIdentity($identity);
            $this->identityHashMap[$identity] = $client;
            return true;
        }
        return false;
    }


    /**
     * 通过套接字名称获取客户端
     *
     * @param string $name
     * @return mixed
     */
    public function getClientSocketByName(string $name): mixed
    {
        return $this->clientSockets[$name] ?? null;
    }


    /**
     * 设置套接字身份 当一个客户端被赋予身份后 将在ServerSocket对象中建立索引 可以快速查找
     *
     * @param mixed  $socket
     * @param string $identity
     * @return mixed
     */
    public function setIdentityBySocket(mixed $socket, string $identity): bool
    {
        if ($client = $this->getClientBySocket($socket)) {
            $client->setIdentity($identity);
            $this->identityHashMap[$identity] = $client;
            return true;
        }
        return false;
    }

    /**
     * 通过套接字获取客户端
     *
     * @param mixed $clientSocket
     * @return \Cclilshy\PRipple\Communication\Socket\Client|null
     */
    public function getClientBySocket(mixed $clientSocket): Client|null
    {
        $name = Manager::getNameBySocket($clientSocket);
        return $this->getClientByName($name);
    }

    /**
     * 通过名称获取客户端
     *
     * @param string $name
     * @return \Cclilshy\PRipple\Communication\Socket\Client|null
     */
    public function getClientByName(string $name): Client|null
    {
        return $this->clientInfos[$name] ?? null;
    }

    /**
     * 通过身份标识获取客户端
     *
     * @param string $name
     * @return \Cclilshy\PRipple\Communication\Socket\Client|null
     */
    public function getClientByIdentity(string $name): Client|null
    {
        return $this->identityHashMap[$name] ?? null;
    }

    /**
     * 移除某个客户端
     *
     * @param mixed $clientSocket
     * @return void
     */
    public function removeClient(mixed $clientSocket): void
    {
        $name = Manager::getNameBySocket($clientSocket);
        if ($clientSocket = $this->clientSockets[$name] ?? null) {
            socket_close($this->clientSockets[$name]);
            unset($this->clientSockets[$name]);
        }

        /**
         * @var \Cclilshy\PRipple\Communication\Aisle\SocketAisle $clientAisle
         */
        if ($clientAisle = $this->clientInfos[$name] ?? null) {
            $identity = $clientAisle->getIdentity();
            if (isset($this->identityHashMap[$identity])) {
                unset($this->identityHashMap[$identity]);
            }
            unset($this->clientInfos[$name]);
        }
    }

    /**
     * 获取所有客户端套接字列表
     *
     * @return array|null
     */
    public function getClientSockets(): array|null
    {
        return $this->clientSockets ?? null;
    }

    public function waitReads(): array|false
    {
        $readSockets   = $this->clientSockets ?? [];
        $readSockets[] = $this->entranceSocket;
        $writeSockets  = null;
        $exceptSockets = null;
        if (socket_select($readSockets, $writeSockets, $exceptSockets, null) > 0) {
            return $readSockets;
        }
        return false;
    }
}
