<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-25 21:59:06
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Service;

use Cclilshy\PRipple\Service\Service;
use Cclilshy\PRipple\Dispatch\Dispatcher;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Communication\Socket\Client;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;
use Cclilshy\PRipple\Communication\Socket\SocketInet;

class TestCast extends Service
{
    public function __construct()
    {
        parent::__construct(SocketInet::class, '0.0.0.0', 3333, [SO_REUSEADDR => 1]);
    }

    public function initialize(): void
    {
        $this->subscribe('Test', 'DEFAULT', Dispatcher::FORMAT_BUILD);
        while (true) {
            $this->publish(new Build($this->publish, null, new Event($this->publish, 'TEST', ''), '测试例子循环消息'));
            usleep(100000);
        }
    }

    public function execMessage(string $message): void
    {
    }

    public function execPackage(Build $package): void
    {

    }

    public function execEvent(Event $event): void
    {

    }

    public function execOriginalContext(string $context, Client $client): void
    {

    }
}