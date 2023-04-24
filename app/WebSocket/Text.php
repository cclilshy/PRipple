<?php

namespace app\WebSocket;

use Cclilshy\PRipple\Communication\Socket\Client;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;
use Cclilshy\PRipple\Communication\Communication;
use Cclilshy\PRipple\Built\WebSocket\WebSocket as WebSocketBase;

class Text extends WebSocketBase
{
    public function __construct()
    {
        parent::__construct('WebSocket');
    }

    /**
     * @return void
     */
    public function initialize(): void
    {
        $this->createServer(Communication::INET, '0.0.0.0', 6789, [SO_REUSEADDR => 1]);
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {

    }

    /**
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function onConnect(Client $client): void
    {
    }

    /**
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function onClose(Client $client): void
    {
    }

    /**
     * @param string                                        $context
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function onMessage(string $context, Client $client): void
    {
        $client->sendWithAgree("recv you say : {$context}");
    }

    /**
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Event $event
     * @return void
     */
    public function onEvent(Event $event): void
    {

    }

    /**
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Build $package
     * @return void
     */
    public function onPackage(Build $package): void
    {

    }

}