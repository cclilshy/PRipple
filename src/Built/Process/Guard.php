<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Built\Process;

use Cclilshy\PRipple\Service\Service;
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

    }

    public function onMessage(string $context, Client $client): void
    {

    }

    public function initialize(): void
    {
        $this->publishEvent('guardOnline', [
            'pid'  => posix_getpid(),
            'ppid' => posix_getppid()
        ]);
    }

    public function onPackage(Build $package): void
    {

    }
}