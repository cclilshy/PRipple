<?php

namespace Cclilshy\PRipple\Built\WebSocket;

use Cclilshy\PRipple\Service\Service;
use Cclilshy\PRipple\Communication\Communication;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;
use Cclilshy\PRipple\Communication\Socket\Client;

abstract class WebSocket extends Service
{
    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    public function initialize(): void
    {
        $this->createServer(Communication::INET, '127.0.0.1', 9111, []);
    }

    public function heartbeat(): void
    {
        // TODO: Implement heartbeat() method.
    }

    public function onConnect(Client $client): void
    {
        Accept::accept($client);
    }

    public function onClose(Client $client): void
    {
        // TODO: Implement onClose() method.
    }

    public function onMessage(string $context, Client $client): void
    {
        // TODO: Implement onMessage() method.
    }

    public function onEvent(Event $event): void
    {
        // TODO: Implement onEvent() method.
    }

    public function onPackage(Build $package): void
    {
        // TODO: Implement onPackage() method.
    }

    public function handshake(string $context, Client $client): void
    {
        if ($info = Accept::verify($client->cache($context))) {
            $client->info = (object)$info;
            $client->handshake();
        }
    }
}