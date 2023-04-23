<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Built\WebSocket;

use Cclilshy\PRipple\Service\Service;
use Cclilshy\PRipple\Communication\Communication;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;
use Cclilshy\PRipple\Communication\Socket\Client;

/**
 *
 */
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
    public function initialize(): void
    {
        $this->createServer(Communication::INET, '127.0.0.1', 9111, []);
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        // TODO: Implement heartbeat() method.
    }

    /**
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function onConnect(Client $client): void
    {
        Accept::accept($client);
    }

    /**
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function onClose(Client $client): void
    {
        // TODO: Implement onClose() method.
    }

    /**
     * @param string                                        $context
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function onMessage(string $context, Client $client): void
    {
        // TODO: Implement onMessage() method.
    }

    /**
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Event $event
     * @return void
     */
    public function onEvent(Event $event): void
    {
        // TODO: Implement onEvent() method.
    }

    /**
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Build $package
     * @return void
     */
    public function onPackage(Build $package): void
    {
        // TODO: Implement onPackage() method.
    }

    /**
     * @param string                                        $context
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function handshake(string $context, Client $client): void
    {
        if ($info = Accept::verify($client->cache($context))) {
            $client->info = (object)$info;
            $client->handshake();
        }
    }
}