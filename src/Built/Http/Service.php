<?php
declare(strict_types=1);
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
use Cclilshy\PRipple\Communication\Communication;
use Cclilshy\PRipple\Service\Service as ServiceBase;
use Cclilshy\PRipple\Built\Http\Event as HttpRequestEvent;

/**
 * @property int    $listen_port
 * @property string $listen_address
 */
class Service extends ServiceBase
{
    private array $requests = array();

    // transfers
    private array $transfers = array();
    // Transmission queue, where upload requests are placed in a transmission queue
    // and the controller is no longer notified

    private HttpRequestEvent $httpRequestEvent;
    private array            $vestigial = array();

    public function __construct()
    {
        parent::__construct('HttpService');
        $this->httpRequestEvent = new HttpRequestEvent($this);
    }

    /**
     * @return void
     */
    public function initialize(): void
    {
        Http::init();
        $this->createServer(Communication::INET, $this->config('listen_address'), $this->config('listen_port'), [
            SO_REUSEADDR => 1,
            TCP_NODELAY  => 1
        ]);
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        foreach ($this->vestigial as $name) {
            unset($this->requests[$name]);
            unset($this->transfers[$name]);
        }
    }

    /**
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Event $event
     * @return void
     */
    public function onEvent(Event $event): void
    {
        $this->httpRequestEvent->extracted($event);
    }

    /**
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Build $package
     * @return void
     */
    public function onPackage(Build $package): void
    {
    }

    /**
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function onConnect(Client $client): void
    {
        $client->setNoBlock();
    }

    /**
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return bool|null
     */
    public function handshake(Client $client): bool|null
    {
        return $client->handshake();
        $client->read($context);
        if (strpos($client->cache($context), "\r\n\r\n")) {
            // TODO: Implement handshake() method.
            $client->info = new Request($client->getName());
            return $client->handshake();
        } else {
            $client->cache($context);
        }
    }

    /**
     * @param string                                        $context
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function onMessage(string $context, Client $client): void
    {
        $clientName = $client->getKeyName();
        if ($transfer = $this->transfers[$clientName] ?? null) {
            if (!$transfer->push($context) || $transfer->getStatusCode() === Request::COMPLETE) {
                unset($this->transfers[$clientName]);
            }
            return;
        } elseif (!isset($this->requests[$clientName]) || $this->requests[$clientName]->getStatusCode() === Request::COMPLETE) {
            $request                     = new Request($clientName);
            $this->requests[$clientName] = $request;
            $request->setClientSocket($client);
        } else {
            $request = $this->requests[$clientName];
        }
        $request->push($context);
        if ($request->getStatusCode() === Request::COMPLETE) {
            $this->httpRequestEvent->access($request);
        } elseif ($request->isUpload === true) {
            $this->httpRequestEvent->access($request);
            $this->transfers[$clientName] = $request;
        }
    }

    /**
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function onClose(Client $client): void
    {
        $this->vestigial[] = $client->getKeyName();
        $this->httpRequestEvent->break($client);
    }
}
