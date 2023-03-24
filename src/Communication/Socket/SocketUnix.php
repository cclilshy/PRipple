<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-18 17:14:37
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */
declare(strict_types=1);

namespace Cclilshy\PRipple\Communication\Socket;

use Exception;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;
use Cclilshy\PRipple\Communication\Standard\AisleInterface;

class SocketUnix
{
    /**
     * @throws \Exception
     */
    public static function createAisle(string $sockFile, int|null $bufferSize = 1024 * 1024): AisleInterface
    {
        $_connect = self::create($sockFile, $bufferSize);
        return SocketAisle::create($_connect);
    }

    /**
     * 创建一个自定义缓冲区大小的UNIX套接字
     *
     * @param string   $sockFile   套接字文件地址
     * @param int|null $bufferSize 默认缓冲区大小为8M
     * @return mixed
     * @throws Exception
     */
    public static function create(string $sockFile, int|null $bufferSize = 1024 * 1024): mixed
    {
        if (file_exists($sockFile)) {
            throw new Exception('无法创建Unix套接字,请关闭正在运行的进程');
        }
        @$sock = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!$sock) {
            throw new Exception('无法创建Unix套接字,请关闭正在运行的进程');
        }
        socket_set_option($sock, SOL_SOCKET, SO_SNDBUF, $bufferSize);
        socket_set_option($sock, SOL_SOCKET, SO_RCVBUF, $bufferSize);
        socket_bind($sock, $sockFile);
        socket_listen($sock);
        return $sock;
    }

    public static function connectAisle(string $sockFile, int|null $bufferSize = 1024 * 1024): AisleInterface
    {
        $_connect = self::connect($sockFile, $bufferSize);
        return SocketAisle::create($_connect);
    }

    /**
     * @param string   $sockFile
     * @param int|null $bufferSize
     * @return mixed
     */
    public static function connect(string $sockFile, int|null $bufferSize = 1024 * 1024): mixed
    {
        $sock = socket_create(AF_UNIX, SOCK_STREAM, 0);
        socket_set_option($sock, SOL_SOCKET, SO_SNDBUF, $bufferSize);
        socket_set_option($sock, SOL_SOCKET, SO_RCVBUF, $bufferSize);
        socket_connect($sock, $sockFile);
        return $sock;
    }
}
