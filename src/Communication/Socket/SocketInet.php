<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-20 09:49:16
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */
declare(strict_types=1);

namespace Cclilshy\PRipple\Communication\Socket;

use Exception;

class SocketInet
{
    /**
     * @throws Exception
     */
    public static function create(string $address, int $port, int|null $type = SOCK_STREAM, array|null $options = []): mixed
    {
        @$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$server) {
            throw new Exception('无法创建INET套接字,请关闭正在运行的进程');
        }
        foreach ($options as $item => $value) {
            socket_set_option($server, SOL_SOCKET, $item, $value);
        }
        if(!socket_bind($server, $address, $port)) {
            throw new Exception("无法绑定套接字地址 > {$address} : {$port}", 1);
        }
        socket_listen($server);
        return $server;
    }
}
