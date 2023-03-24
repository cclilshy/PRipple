<?php

namespace Cclilshy\PRipple\Communication\Socket;
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-22 15:36:03
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

class ServerSocketManager
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
     * @throws \Exception
     */
    public static function createServer(string $type, string $address, int|null $port = 0, array|null $options = array()): ServerSocketManager|false
    {
        switch ($type) {
            case SocketInet::class:
                $server = SocketInet::create($address, $port, null, $options);
                return new self($server);
            case SocketUnix::class:
                $server = SocketUnix::create($address);
                return new self($server);
            default:
                return false;
        }
    }

    public function getEntranceSocket(): mixed
    {
        return $this->entranceSocket;
    }

    public function accept(mixed $socket): string|false
    {
        if ($client = socket_accept($socket)) {
            return $this->addClient($client);
        }
        return false;
    }

    public function addClient(mixed $clientSocket): string
    {
        $name                       = ServerSocketManager::getNameBySocket($clientSocket);
        $this->clientSockets[$name] = $clientSocket;
        $this->clientInfos[$name]   = new ClientIter($clientSocket);
        return $name;
    }

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
        }
    }


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
        }
    }

    public function getClientBySocket(mixed $clientSocket): ClientIter|null
    {
        $name = ServerSocketManager::getNameBySocket($clientSocket);
        return $this->getClientByName($name);
    }

    public function getClientByName(string $name): ClientIter|null
    {
        return $this->clientInfos[$name] ?? null;
    }

    public function getClientByIdentity(string $name): ClientIter|null
    {
        return $this->identityHashMap[$name] ?? null;
    }

    public function removeClient(mixed $clientSocket): void
    {
        $name = ServerSocketManager::getNameBySocket($clientSocket);
        if ($clientSocket = $this->clientSockets[$name] ?? null) {
            socket_close($this->clientSockets[$name]);
            unset($this->clientSockets[$name]);
        }

        /**
         * @var \Cclilshy\PRipple\Communication\Aisle\SocketAisle $clientAisle
         */
        if($clientAisle = $this->clientInfos[$name] ?? null){
            $identity = $clientAisle->getIdentity();
            if(isset($this->clientInfos[$identity])){
                unset($this->clientInfos[$identity]);
            }
            unset($this->clientInfos[$name]);
        }
    }

    public function getClientSockets(): array|null
    {
        return $this->clientSockets ?? null;
    }
}
