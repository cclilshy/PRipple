<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Built\WebSocket;

use Cclilshy\PRipple\Service\Service;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;
use Cclilshy\PRipple\Communication\Socket\Client;


abstract class WebSocket extends Service
{
    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    /**
     * @return void
     */
    abstract public function initialize(): void;

    /**
     * @return void
     */
    abstract public function heartbeat(): void;

    /**
     * @param Client $client
     * @return void
     */
    abstract public function onConnect(Client $client): void;

    /**
     * @param Client $client
     * @return void
     */
    abstract public function onClose(Client $client): void;

    /**
     * @param string $context
     * @param Client $client
     * @return void
     */
    abstract public function onMessage(string $context, Client $client): void;

    /**
     * @param Event $event
     * @return void
     */
    abstract public function onEvent(Event $event): void;

    /**
     * @param Build $package
     * @return void
     */
    abstract public function onPackage(Build $package): void;

    /**
     * @param Client $client
     * @return bool|null
     */
    public function handshake(Client $client): bool|null
    {
        return Accept::accept($client);
    }
}