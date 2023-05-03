<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-21 20:32:14
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Service;

use Cclilshy\PRipple\Communication\Socket\Client;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;


interface ServiceStandard
{
    /**
     * execute after the service starts
     *
     * @return void
     */
    public function initialize(): void;

    /**
     * heartbeat
     *
     * @return void
     */
    public function heartbeat(): void;

    /**
     * Handle native message packets
     *
     * @param Build $package
     * @return void
     */
    public function onPackage(Build $package): void;

    /**
     * Handle event type messages
     *
     * @param Event $event
     * @return void
     */
    public function onEvent(Event $event): void;

    /**
     * Fired when a new connection is made
     *
     * @param Client $client
     * @return void
     */
    public function onConnect(Client $client): void;

    /**
     * Process server packets
     *
     * @param string $context
     * @param Client $client
     * @return void
     */
    public function onMessage(string $context, Client $client): void;

    /**
     * Triggered when the connection is disconnected
     *
     * @param Client $client
     * @return void
     */
    public function onClose(Client $client): void;

    /**
     * Triggers for requests that fail the handshake
     *
     * @param Client $client
     * @return bool|null
     */
    public function handshake(Client $client): bool|null;

    public function destroy(): void;
}