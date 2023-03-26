<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-16 22:33:59
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: CCPHP
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */
namespace Cclilshy\PRipple\Communication\Aisle;

use Cclilshy\PRipple\Communication\Standard\CommunicationInterface;

class SocketAisle implements CommunicationInterface
{
    const EXT = '.aisle';

    private readonly string $address;    // 用户地址
    private readonly string $keyName;    // 在管理器中的键名
    private readonly int    $createTime; // 用户连接时
    private readonly mixed  $socket;     // 套接字实体
    private readonly int    $port;

    private int $sendBufferSize;
    // 发送缓冲区大小
    private int $receiveBufferSize;
    // 接收缓冲区大小
    private int $sendLowWaterSize;
    // 发送低水位大小
    private int $receiveLowWaterSize;
    // 接收低水位大小
    private int $sendFlowCount = 0;
    // 总共接收流量
    private int $receiveFlowCount = 0;
    // 总共发送流量
    private string $sendBuffer = '';
    // 发送丢包储存区

    private string $name;        // 自定义的名称
    private string $identity;    // 自定义身份标识
    private int    $activeTime;  // 上次活跃时间

    public function __construct(mixed $socket)
    {
        socket_getsockname($socket, $address, $port);
        $this->address    = $address;
        $this->port       = $port ?? 0;
        $this->keyName    = spl_object_hash($socket);
        $this->createTime = time();
        $this->socket     = $socket;
        $this->name       = '';
        $this->activeTime = time();

        $this->sendBufferSize      = socket_get_option($socket, SOL_SOCKET, SO_SNDBUF);
        $this->receiveBufferSize   = socket_get_option($socket, SOL_SOCKET, SO_RCVBUF);
        $this->sendLowWaterSize    = socket_get_option($socket, SOL_SOCKET, SO_SNDLOWAT);
        $this->receiveLowWaterSize = socket_get_option($socket, SOL_SOCKET, SO_RCVLOWAT);
    }

    /**
     * 创建连接
     *
     * @param mixed $base
     * @return false|static
     */
    public static function create(mixed $base): self|false
    {
        return new self($base);
    }


    /**
     * 不可以直接连接
     *
     * @param string $name
     * @return false|static
     */
    public static function link(string $name): self|false
    {
        return false;
    }


    /**
     * 获取在管理器中的键名
     *
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->keyName;
    }


    /**
     * 获取客户端连接时间
     *
     * @return int
     */
    public function getCreateTime(): int
    {
        return $this->createTime;
    }


    /**
     * 获取socket实体
     *
     * @return mixed
     */
    public function getSocket(): mixed
    {
        return $this->socket;
    }

    /**
     * 获取客户端地址
     *
     * @return string
     */
    public function getAddress(): string
    {
        return $this->address;
    }


    //     设置和获取自定义信息

    /**
     * 获取客户端端口
     *
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }


    /**
     * 获取客户端名称
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * 设置客户端名称
     *
     * @param string $name
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }


    /**
     * 获取客户端身份标识
     *
     * @return string
     */
    public function getIdentity(): string
    {
        return $this->identity ?? '';
    }

    /**
     * 设置客户端身份标识
     *
     * @param string $identity
     * @return void
     */
    public function setIdentity(string $identity): void
    {
        $this->identity = $identity;
    }

    /**
     * 获取发送总流量
     *
     * @return int
     */
    public function getSendFlowCount(): int
    {
        return $this->sendFlowCount;
    }


    /**
     * 获取接收缓冲区大小
     *
     * @return int
     */
    public function getReceiveFlowCount(): int
    {
        return $this->receiveFlowCount;
    }


    /**
     * 获取发送缓冲区大小
     *
     * @return int
     */
    public function getSendBufferSize(): int
    {
        return $this->sendBufferSize;
    }


    /**
     * 设置发送缓冲区大小
     *
     * @param int $size
     * @return bool
     */
    public function setSendBufferSize(int $size): bool
    {
        if (socket_set_option($this->socket, SOL_SOCKET, SO_SNDBUF, $size)) {
            $this->sendBufferSize = $size;
            return true;
        }
        return false;
    }


    /**
     * 获取发送低水位大小
     *
     * @return int
     */
    public function getSendLowWaterSize(): int
    {
        return $this->sendLowWaterSize;
    }


    /**
     * 设置发送低水位大小
     *
     * @param int $size
     * @return bool
     */
    public function setSendLowWaterSize(int $size): bool
    {
        if (socket_set_option($this->socket, SOL_SOCKET, SO_SNDLOWAT, $size)) {
            $this->sendLowWaterSize = $size;
            return true;
        }
        return false;
    }


    /**
     * 获取接收低水位大小
     *
     * @return int
     */
    public function getReceiveLowWaterSize(): int
    {
        return $this->receiveLowWaterSize;
    }


    /**
     * 设置接收低水位大小
     *
     * @param int $size
     * @return bool
     */
    public function setReceiveLowWaterSize(int $size): bool
    {
        if (socket_set_option($this->socket, SOL_SOCKET, SO_RCVLOWAT, $size)) {
            $this->receiveLowWaterSize = $size;
            return true;
        }
        return false;
    }


    /**
     * 获取接收缓冲区大小
     *
     * @return int
     */
    public function getReceiveBufferSize(): int
    {
        return $this->receiveBufferSize;
    }


    /**
     * 设置接收缓冲区大小
     *
     * @param int $size
     * @return bool
     */
    public function setReceiveBufferSize(int $size): bool
    {
        if (socket_set_option($this->socket, SOL_SOCKET, SO_RCVBUF, $size)) {
            $this->receiveBufferSize = $size;
            return true;
        }
        return false;
    }


    /**
     * 获取上次活跃时间
     *
     * @return int
     */
    public function getActiveTime(): int
    {
        return $this->activeTime;
    }


    /**
     * 读取数据
     *
     * @param null     $data
     * @param int|null $length
     * @return bool
     */
    public function read(mixed &$data, int|null $length = null): bool
    {
        $this->activeTime = time();
        if (!$length) {
            $length = $this->receiveBufferSize;
            $target = false;
        } else {
            // 严格接收模式
            $target = true;
        }
        $data          = '';
        $handledLength = 0;
        do {
            $readList = [$this->socket];
            if (!socket_select($readList, $_, $_, null)) {
                break;
            }
            $recLength = socket_recv($this->socket, $_buffer, min($length, $this->receiveBufferSize), 0);
            if ($recLength === false || $recLength === 0 || $_buffer === null) {
                break;
            }
            $length                 -= $recLength;
            $data                   .= $_buffer;
            $handledLength          += $recLength;
            $this->receiveFlowCount += $recLength;
        } while ($length > 0 && $target);

        if ($target) {
            return $length === 0;
        } else {
            return $handledLength > 0;
        }
    }


    /**
     * 写入数据
     *
     * @param string $context
     * @param        $handledLength
     * @return bool
     */
    public function write(string $context, &$handledLength): bool
    {
        $this->activeTime = time();
        $handledLength    = 0;
        $tasks            = str_split($context, $this->sendBufferSize);    // 切片
        do {
            if ($task = array_shift($tasks)) {
                $this->sendBuffer .= $task;
            }
            $writeList = [$this->socket];   // 可写列表
            socket_select($_, $writeList, $_, null);
            $_buffer     = substr($this->sendBuffer, 0, $this->sendBufferSize);
            $writeLength = socket_send($this->socket, $_buffer, strlen($_buffer), 0);
            if ($writeLength === false || $writeLength === 0 || $_buffer === null) {
                return false;
            }
            $handledLength       += $writeLength;
            $this->sendBuffer    = substr($_buffer, $writeLength);
            $this->sendFlowCount += $writeLength;
        } while (!empty($buffer) || count($tasks) > 0);
        return $writeLength === strlen($context);
    }

    /**
     * 关闭套接字连接
     *
     * @return bool
     */
    public function release(): bool
    {
        return $this->close();
    }


    /**
     * 关闭连接
     *
     * @return bool
     */
    public function close(): bool
    {
        socket_close($this->socket);
        return true;
    }
}
