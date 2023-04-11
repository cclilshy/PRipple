<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-23 12:42:40
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Communication\Socket;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;

class Client extends SocketAisle
{
    public string    $verifyBuffer;
    public string    $socketType;
    public bool      $verify;
    public string    $cache;
    public \stdClass $info;

    public function __construct(mixed $socket, string $type, Manager $manager)
    {
        parent::__construct($socket, $manager);
        $this->socketType   = $type;
        $this->verifyBuffer = '';
        $this->verify       = false;
        $this->info         = new \stdClass();
        $this->cache        = '';
    }

    /**
     * 客户端数据缓存区
     *
     * @param string|null $context
     * @return string
     */
    public function cache(string|null $context = null): string
    {
        if ($context !== null) {
            $this->cache .= $context;
        }
        return $this->cache;
    }

    /**
     * 建立握手
     *
     * @return void
     */
    public function handshake(): void
    {
        $this->verify = true;
    }

    /**
     * 屏蔽客户端
     *
     * @return void
     */
    public function disable(): void
    {
        $this->manager->removeClient($this->socket);
    }
}