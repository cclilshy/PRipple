<?php
declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-16 22:33:59
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: PRipple
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Communication\Aisle;

use Exception;
use Cclilshy\PRipple\FileSystem\File;
use Cclilshy\PRipple\Communication\Socket\Manager;
use Cclilshy\PRipple\Communication\Standard\CommunicationInterface;

/**
 *
 */
class SocketAisle implements CommunicationInterface
{
    public const EXT = '.sock';
    public Manager            $manager;        // 用户地址
    protected readonly string $address;        // 在管理器中的键名
    protected readonly string $keyName;        // 用户连接时
    protected readonly int    $createTime;     // 套接字实体
    protected readonly mixed  $socket;
    protected readonly int    $port;
    // 发送缓冲区大小
    protected int $sendBufferSize;
    // 接收缓冲区大小
    protected int $receiveBufferSize;
    // 发送低水位大小
    protected int $sendLowWaterSize;
    // 接收低水位大小
    protected int $receiveLowWaterSize;
    // 总共接收流量
    protected int $sendFlowCount = 0;
    // 总共发送流量
    protected int $receiveFlowCount = 0;
    // 发送丢包储存区
    protected string $sendBuffer = '';
    // 文件缓冲区
    protected FileAisle $cacheFile;
    // 文件缓冲区文件路径
    protected string $cacheFilePath;
    // 文件缓冲长度
    protected int $cacheLength = 0;
    // 缓存指针位置
    protected int    $cachePoint = 0;
    protected string $name;        // 自定义的名称
    protected string $identity;    // 自定义身份标识
    protected int    $activeTime;  // 上次活跃时间

    /**
     * @throws \Exception
     */
    public function __construct(mixed $socket, Manager|null $manager = null)
    {
        socket_getsockname($socket, $address, $port);
        if ($manager) {
            $this->manager = $manager;
        }
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
        $this->cacheFilePath       = PRIPPLE_CACHE_PATH . '/socket_cache_' . $this->keyName . microtime(true) . mt_rand(1111, 9999) . self::EXT;
        if (File::exists($this->cacheFilePath)) {
            unlink($this->cacheFilePath);
        }
        if ($cacheFile = File::create($this->cacheFilePath, 'r+')) {
            $this->cacheFile = FileAisle::create($cacheFile);
        } else {
            throw new Exception("无法创建套接字缓存缓冲文件,请检查目录权限 " . $this->cacheFilePath);
        }
    }

    /**
     * 创建连接
     *
     * @param mixed $base
     * @return false|static
     * @throws \Exception
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
     * 非堵塞型写
     *
     * @param string $context
     * @return int|bool
     */
    public function write(string $context): int|bool
    {
        $handledLengthCount = 0;
        // 处理缓冲区数据
        $list = str_split($this->sendBuffer, $this->sendBufferSize);
        while ($item = array_shift($list)) {
            if (!$handledLength = socket_send($this->socket, $item, strlen($item), 0)) {
                $this->cacheToFile($context);
                return $handledLengthCount;
            } else {
                $this->sendBuffer   = substr($this->sendBuffer, $handledLength);
                $handledLengthCount += $handledLength;
            }
        }

        // 处理缓存文件数据
        if ($this->cacheLength > 0) {
            $this->cacheFile->adjustPoint($this->cachePoint);
            while ($this->cacheLength > 0 && $this->cacheFile->read($cacheContextFragment, min($this->sendBufferSize, $this->cacheLength))) {
                if (!$handledLength = socket_send($this->socket, $cacheContextFragment, strlen($cacheContextFragment), 0)) {
                    $this->cacheToFile($context);
                    return $handledLengthCount;
                } else {
                    $this->cachePoint   += $handledLength;
                    $this->cacheLength  -= $handledLength;
                    $handledLengthCount += $handledLength;
                }
            }
            if (isset($this->manager)) {
                $this->manager->removeClientWithBufferedData($this);
            }
        }

        // 处理请求文本
        $list = str_split($context, $this->sendBufferSize);
        while ($item = array_shift($list)) {
            if (!$handledLength = socket_send($this->socket, $item, strlen($item), 0)) {
                $this->cacheToFile($item);
                while ($item = array_shift($list)) {
                    $this->cacheToFile($item);
                }
                return $handledLengthCount;
            } else {
                $handledLengthCount += $handledLength;
            }
        }
        return $handledLengthCount;
    }

    /**
     * 写缓冲到文件缓冲区
     *
     * @param string $context
     * @return void
     */
    public function cacheToFile(string $context): void
    {
        $this->cacheFile->adjustPoint(0, SEEK_END);
        $this->cacheFile->write($context);
        $this->cacheLength += strlen($context);
        if (isset($this->manager)) {
            $this->manager->addClientWithBufferedData($this);
        }
    }

    /**
     * 实时读取数据,数据完整但堵塞
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
            if (!socket_select($readList, $_, $_, null, 1000)) {
                break;
            }
            @$recLength = socket_recv($this->socket, $_buffer, min($length, $this->receiveBufferSize), 0);
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
     * 关闭套接字连接
     *
     * @return bool
     */
    public function release(): bool
    {
        $this->close();
        $this->cacheFile->close();
        $this->cacheFile->release();
        unlink($this->cacheFilePath);
        return true;
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

    /**
     * 设置为堵塞模式
     *
     * @return bool
     */
    public function setBlock(): bool
    {
        return socket_set_block($this->socket);
    }

    /**
     * 设置为非堵塞模式
     *
     * @return bool
     */
    public function setNoBlock(): bool
    {
        return socket_set_nonblock($this->socket);
    }

    /**
     * 堵塞推送数据
     *
     * @return int|false
     */
    public function truncate(): int|false
    {
        $handledLengthCount = 0;
        // 处理缓冲区数据
        $handledLengthCount += $this->send($this->sendBuffer);

        // 处理缓存文件数据
        if ($this->cacheLength > 0) {
            $this->cacheFile->adjustPoint($this->cachePoint);
            $cacheContextFragment = '';
            while ($this->cacheLength > 0 && $this->cacheFile->read($cacheContextFragment, min($this->sendBufferSize, $this->cacheLength))) {
                $handledLength      = $this->send($cacheContextFragment);
                $this->cacheLength  -= $handledLength;
                $handledLengthCount += $handledLength;
            }
        }

        return $handledLengthCount;
    }

    /**
     * 实时写入数据,数据完整但堵塞
     *
     * @param string $context
     * @return int|false
     */
    public function send(string $context): int|false
    {
        $this->activeTime = time();
        $handledLength    = 0;
        $tasks            = str_split($context, $this->sendBufferSize);    // 切片
        do {
            if ($task = array_shift($tasks)) {
                $this->sendBuffer .= $task;
            }
            $writeList = [$this->socket];
            socket_select($_, $writeList, $_, null, 1000);
            $_buffer     = substr($this->sendBuffer, 0, $this->sendBufferSize);
            $writeLength = socket_send($this->socket, $_buffer, strlen($_buffer), 0);
            if ($writeLength === false || $writeLength === 0 || $_buffer === null) {
                return false;
            }
            $handledLength       += $writeLength;
            $this->sendBuffer    = substr($_buffer, $writeLength);
            $this->sendFlowCount += $writeLength;
        } while (!empty($buffer) || count($tasks) > 0);
        return $handledLength;
    }

    /**
     * 通过协议发送数据
     *
     * @param string $agree
     * @param string $method
     * @param array  $options
     * @return mixed
     */
    public function sendByAgree(string $agree, string $method, array $options): mixed
    {
        return call_user_func_array([$agree, $method], array_merge([$this], $options));
    }

    /**
     * 获取文件缓冲区长度
     *
     * @return int
     */
    public function getCacheLength(): int
    {
        return $this->cacheLength;
    }

    /**
     * @return string[]
     */
    public function __sleep()
    {
        return [
            'address',
            'keyName',
            'createTime',
            'port',
            'sendBufferSize',
            'receiveBufferSize',
            'sendLowWaterSize',
            'receiveLowWaterSize',
            'sendFlowCount',
            'receiveFlowCount',
            'sendBuffer',
            'cacheLength',
            'cachePoint',
            'name',
            'identity',
            'cacheFilePath',
            'activeTime'
        ];
    }
}
