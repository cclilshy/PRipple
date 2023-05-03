<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);

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
     * @param Client $client
     * @return void
     */
    public function onConnect(Client $client): void
    {
    }

    /**
     * @param Client $client
     * @return void
     */
    public function onClose(Client $client): void
    {
    }

    /**
     * @param string $context
     * @param Client $client
     * @return void
     */
    public function onMessage(string $context, Client $client): void
    {
        $client->sendWithAgree("recv you say : {$context}");
    }

    /**
     * @param Event $event
     * @return void
     */
    public function onEvent(Event $event): void
    {

    }

    /**
     * @param Build $package
     * @return void
     */
    public function onPackage(Build $package): void
    {

    }

}