<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-30 14:10:12
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Http;

use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;
use Cclilshy\PRipple\Communication\Socket\Client;
use Cclilshy\PRipple\Service\Service as ServiceBase;
use Cclilshy\PRipple\Communication\Socket\SocketInet;
use Cclilshy\PRipple\Built\Http\Event as HttpRequestEvent;

class Service extends ServiceBase
{


    private array            $requests  = array();
    private array            $transfers = array();
    private HttpRequestEvent $httpRequestEvent;

    public function __construct()
    {
        parent::__construct('HttpService');
        $this->httpRequestEvent = new HttpRequestEvent($this);
    }

    public function initialize(): void
    {
        Http::init();
        $this->createServer(SocketInet::class, '0.0.0.0', 2222, [SO_REUSEADDR => 1]);
    }

    public function heartbeat(): void
    {
        // $this->httpRequestEvent->handle();
    }

    public function onEvent(Event $event): void
    {
        $this->httpRequestEvent->extracted($event);
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
        if ($transfer = $this->transfers[$clientName] ?? null) {
            if (!$transfer->push($context) || $transfer->getStatusCode() == Request::COMPLETE) {
                unset($this->transfers[$clientName]);
            }
            return;
        } elseif (!$request = $this->requests[$clientName] ?? null) {
            $request                     = new Request($clientName);
            $this->requests[$clientName] = $request;
            $request->setClientSocket($client);
        }

        $request->push($context);

        if ($request->getStatusCode() == Request::COMPLETE) {
            $this->httpRequestEvent->access($request);
            unset($this->requests[$clientName]);
            unset($this->transfers[$clientName]);
        } elseif ($request->isUpload === true) {
            $this->httpRequestEvent->access($request);
            $this->transfers[$clientName] = $request;
            unset($this->requests[$clientName]);
        }

    }

    public function onClose(Client $client): void
    {
        unset($this->requests[$client->getKeyName()]);
        unset($this->transfers[$client->getKeyName()]);
        $this->httpRequestEvent->break($client);
    }
}
