<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-30 14:10:12
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Http;

use Fiber;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;
use Cclilshy\PRipple\Communication\Socket\Client;
use Cclilshy\PRipple\Service\Service as ServiceBase;
use Cclilshy\PRipple\Communication\Socket\SocketInet;

class Service extends ServiceBase
{


    private array $requests = array();

    public function __construct()
    {
        parent::__construct();
    }


    public function initialize(): void
    {
        Http::init();
        $this->createServer(SocketInet::class, '0.0.0.0', 2222, [SO_REUSEADDR => 1]);
    }

    public function onEvent(Event $event): void
    {

    }

    public function onPackage(Build $package): void
    {

    }

    public function onConnect(Client $client): void
    {
        $client->setNoBlock();
    }

    public function onMessage(string $context, Client $client): void
    {
        $clientName = $client->getKeyName();
        if (!isset($this->requests[$clientName])) {
            $this->requests[$clientName] = new Request($clientName);
        }
        $request = $this->requests[$clientName];
        if (!$request->push($context)) {
            unset($this->requests[$clientName]);
        }
        if ($request->getStatusCode() == Request::COMPLETE) {
            $response = $request->go();
            $client->write($response->__toString());
            unset($this->requests[$clientName]);
        }
    }

    public function onClose(Client $client): void
    {

    }
}