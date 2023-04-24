<?php
declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-23 12:42:40
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Communication\Socket;

use Cclilshy\PRipple\Communication\Aisle\SocketAisle;

/**
 *
 */
class Client extends SocketAisle
{
    public string $verifyBuffer;
    public string $socketType;
    public bool   $verify;
    public string $cache;
    public mixed  $info;
    public string $agree;

    /**
     * @param mixed                                          $socket
     * @param string                                         $type
     * @param \Cclilshy\PRipple\Communication\Socket\Manager $manager
     * @throws \Exception
     */
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
     * @param string|null $agree
     * @return true
     */
    public function handshake(string|null $agree = null): true
    {
        if ($agree) {
            $this->agree = $agree;
        }
        return $this->verify = true;
    }

    /**
     * 切断客户端连接
     *
     * @return void
     */
    public function cutConnect(): void
    {
        $this->manager->removeClient($this->socket);
    }

    /**
     * 清空缓存区
     *
     * @return void
     */
    public function cleanCache(): void
    {
        $this->cache = '';
    }

    public function getPlaintext(): string|false
    {
        $this->read($context);
        if (isset($this->agree)) {
            if ($result = call_user_func([$this->agree, 'parse'], $this->cache($context))) {
                $this->cleanCache();
                return $result;
            }
        } else {
            return $context;
        }
        return false;
    }

    /**
     * 通过协议发送
     *
     * @param string $context
     * @return bool
     */
    public function sendWithAgree(string $context): bool
    {
        return call_user_func([$this->agree, 'send'], $this, $context);
    }

}