<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);
namespace Cclilshy\PRipple\Built\Process;

use Cclilshy\PRipple\Service\Service;
use Cclilshy\PRipple\Dispatch\Dispatcher;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Communication\Socket\Client;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;

class Guard extends Service
{
    public function __construct(string|null $name = null)
    {
        parent::__construct($name);
    }

    public function handshake(Client $client): bool|null
    {

    }

    public function onConnect(Client $client): void
    {

    }

    public function onClose(Client $client): void
    {

    }

    public function heartbeat(): void
    {

    }

    public function onEvent(Event $event): void
    {
        if ($event->getName() === $this->name . '_signal') {
            $data = $event->getData();
            posix_kill($data['pid'], $data['signNo']);
        }
    }

    public function onMessage(string $context, Client $client): void
    {

    }

    public function initialize(): void
    {
        $this->subscribe('Process', $this->name . '_signal', Dispatcher::FORMAT_EVENT);
    }

    public function onPackage(Build $package): void
    {

    }

    public function destroy(): void
    {

    }
}