<?php

namespace Service;

use Cclilshy\PRipple\Dispatch\Build;
use Cclilshy\PRipple\Service\Service;
use Cclilshy\PRipple\Communication\Socket\SocketInet;
use Cclilshy\PRipple\Dispatch\EventTemplate\CommonTemplate;
use Cclilshy\PRipple\Dispatch\Standard\EventTemplateAbstract;

class TestCast extends Service
{
    public function __construct()
    {
        parent::__construct(SocketInet::class, '0.0.0.0', 3333, [SO_REUSEADDR => 1]);
    }

    public function initialize(): void
    {
        while (true) {
            $this->publish(new Build($this->publish, null, new CommonTemplate($this->publish, 'TEST', ''), '测试例子循环消息'));
            sleep(1);
        }
    }

    public function execMessage(string $message): void
    {
    }

    public function execPackage(Build $package): void
    {

    }

    public function execEvent(EventTemplateAbstract $event): void
    {

    }

    public function execOriginalContext(string $context): void
    {

    }
}