<?php
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

/**
 *
 */
interface ServiceStandard
{
    /**
     * 服务启动后执行
     *
     * @return void
     */
    public function initialize(): void;

    /**
     * 心跳
     *
     * @return void
     */
    public function heartbeat(): void;

    /**
     * 处理原生消息包
     *
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Build $package
     * @return void
     */
    public function onPackage(Build $package): void;

    /**
     * 处理事件类型消息
     *
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Event $event
     * @return void
     */
    public function onEvent(Event $event): void;

    /**
     * 新连接时触发
     *
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function onConnect(Client $client): void;

    /**
     * 处理服务器报文
     *
     * @param string                                        $context
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function onMessage(string $context, Client $client): void;

    /**
     * 当连接断开时触发
     *
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function onClose(Client $client): void;

    /**
     * 对于未通过握手的请求会触发
     *
     * @param string                                        $context
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function handshake(string $context, Client $client): void;
}