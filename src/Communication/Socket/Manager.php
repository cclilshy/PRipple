<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);

namespace Cclilshy\PRipple\Communication\Socket;

use Cclilshy\PRipple\Log;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;


class Manager
{
    private mixed  $entranceSocket;
    private array  $clientSockets;
    private array  $clients;
    private array  $identityHashMap;
    private array  $bufferClientList;
    private array  $bufferSocketList;
    private string $socketType;

    /**
     * @param mixed  $entranceSocket
     * @param string $socketType
     */
    public function __construct(mixed $entranceSocket, string $socketType)
    {
        $this->entranceSocket = $entranceSocket;
        $this->socketType     = $socketType;
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
                return new self($server, $type);
            case SocketUnix::class:
                $server = SocketUnix::create($address);
                return new self($server, $type);
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
     * @throws \Exception
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
     * @throws \Exception
     */
    public function addClient(mixed $clientSocket): string
    {
        $name                       = Manager::getNameBySocket($clientSocket);
        $this->clientSockets[$name] = $clientSocket;
        $this->clients[$name]       = new Client($clientSocket, $this->socketType, $this);
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
        return $this->clients[$name] ?? null;
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
        unset($this->clientSockets[$name]);

        /**
         * @var SocketAisle $clientAisle
         */
        if ($clientAisle = $this->clients[$name] ?? null) {
            $clientAisle->release();
            $this->removeClientWithBufferedData($clientAisle);
            if ($identity = $clientAisle->getIdentity()) {
                unset($this->identityHashMap[$identity]);
            }
            unset($this->clients[$name]);
        }
    }

    /**
     * 移除标记客户端有缓冲数据
     *
     * @param SocketAisle $client
     * @return void
     */
    public function removeClientWithBufferedData(SocketAisle $client): void
    {
        if (isset($this->bufferClientList[$client->getKeyName()])) {
            unset($this->bufferClientList[$client->getKeyName()]);
        }
        if (isset($this->bufferSocketList[$client->getKeyName()])) {
            unset($this->bufferSocketList[$client->getKeyName()]);
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

    /**
     * 等待新连接
     *
     * @param int|null $microsecond
     * @return array|false
     */
    public function waitReads(int|null $microsecond = 1000000): array|false
    {
        $readSockets   = $this->clientSockets ?? [];
        $readSockets[] = $this->entranceSocket;
        $writeSockets  = [];
        $exceptSockets = [];
        if (socket_select($readSockets, $writeSockets, $exceptSockets, 0, $microsecond)) {
            return $readSockets;
        }
        return false;
    }

    /**
     * 标记客户端有缓冲数据
     *
     * @param SocketAisle $client
     * @return void
     */
    public function addClientWithBufferedData(SocketAisle $client): void
    {
        $this->bufferClientList[$client->getKeyName()] = $client;
        $this->bufferSocketList[$client->getKeyName()] = $client->getSocket();
    }

    /**
     * 处理所有缓冲客户端数据
     *
     * @return void
     */
    public function handleBufferContext(): void
    {
        if ($buffers = $this->waitBuffers()) {
            foreach ($buffers as $socket) {
                $client = $this->getClientBySocket($socket);
                if ($len = $client->write("")) {
                    Log::pdebug('[SocketServer]', $client->getKeyName(), "成功推送缓冲数据>{$len}");
                }
            }
        }
    }

    /**
     * 等待缓冲客户端可写
     *
     * @param int|null $microsecond
     * @return array|false
     */
    public function waitBuffers(int|null $microsecond = 1000000): array|false
    {
        $readSockets   = [];
        $writeSockets  = $this->bufferSocketList ?? [];
        $exceptSockets = [];

        if (count($writeSockets) > 0 && socket_select($readSockets, $writeSockets, $exceptSockets, 0, $microsecond)) {
            return $writeSockets;
        }
        return false;
    }

    /**
     * @return array|null
     */
    public function getClients(): array|null
    {
        return $this->clients ?? null;
    }
}