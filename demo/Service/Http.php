<?php

namespace Service;

use Cclilshy\PRipple\Console;
use Cclilshy\PRipple\Dispatch\Build;
use Cclilshy\PRipple\Service\Service;
use Cclilshy\PRipple\Dispatch\Dispatcher;
use Cclilshy\PRipple\Communication\Socket\SocketInet;
use Cclilshy\PRipple\Dispatch\Standard\EventTemplateAbstract;

class Http extends Service
{
    public function __construct()
    {
        parent::__construct(SocketInet::class, '0.0.0.0', 2222, [SO_REUSEADDR => 1]);
        $this->launch();
    }

    public function initialize(): void
    {
        $this->subscribe('Service_TestCast', 'DEFAULT', Dispatcher::FORMAT_BUILD);
        declare(ticks=1);
        pcntl_signal(SIGINT, function () {
            $this->noticeClose();
            exit;
        });
    }

    public function execMessage(string $message): void
    {
        Console::debug('收到订阅消息 > ' . $message);
    }

    public function execPackage(Build $package): void
    {
        Console::debug('收到订阅包 > ' . $package);
    }

    public function execEvent(EventTemplateAbstract $event): void
    {
        Console::debug('收到订阅事件 > ' ,$event);
    }

    public function execOriginalContext(string $context): void
    {

    }

    public function exceptHandler(mixed $e)
    {

    }
}