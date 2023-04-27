<?php
declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-22 01:15:45
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Service;

use Exception;
use Cclilshy\PRipple\Log;
use Cclilshy\PRipple\Dispatch\Dispatcher;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Communication\Socket\Manager;
use Cclilshy\PRipple\Communication\Aisle\SocketAisle;
use Cclilshy\PRipple\Communication\Socket\SocketUnix;

/**
 * event handler development dependencies
 */
abstract class Service extends ServiceInfo implements ServiceStandard
{
    public const PS_START = 'PS_START';
    public const PS_CLOSE = 'PS_CLOSE';
    public const PC_CLOSE = 'PC_CLOSE';

    public Manager     $serverSocketManager;
    public SocketAisle $dispatcherServerAisle;
    public string      $publish;
    public mixed       $dispatcherServer;
    public array       $socketTypeMap;

    public string $socketType;
    public string $serverAddress;
    public int    $serverPort;
    public array  $socketOptions;
    public int    $selectBlockLine;
    public bool   $isServer = false;

    /**
     * service configuration
     */
    public function __construct(string|null $name = null)
    {
        if ($name !== null) {
            $this->publish = $name;
        } else {
            $this->publish = get_class($this);
            $this->publish = str_replace('\\', '_', $this->publish);
        }
        parent::__construct($this->publish);
    }

    /**
     * start service
     *
     * @return void
     */
    public function launch(): void
    {
        if (!$this->initCreate()) {
            die("server {$this->name} launch failed!\n");
        }
        $this->registerErrorHandler();
        try {
            if (!$this->reConnectDispatcher()) {
                throw new Exception("If the scheduler cannot be connected, make sure that the scheduler starts normally");
            }
            if ($this->isServer) {
                $this->serverSocketManager = Manager::createServer($this->socketType, $this->serverAddress, $this->serverPort, $this->socketOptions);
            }
        } catch (Exception $e) {
            Log::pdebug("[Service]", $e->getMessage());
            return;
        }

        while (true) {
            if (memory_get_usage() > strToBytes(ini_get('memory_limit')) * 0.8) {
                $this->heartbeat();
                gc_collect_cycles();
            }
            $readList = array($this->dispatcherServer);
            if ($this->isServer) {
                $readList = array_merge($readList, [$this->serverSocketManager->getEntranceSocket()], $this->serverSocketManager->getClientSockets() ?? []);
            }

            $this->selectBlockLine = __LINE__ + 1;
            if (socket_select($readList, $_, $_, 0, 1000000)) {
                foreach ($readList as $readSocket) {
                    if (!$this->isServer) {
                        $this->handlerDispatcherMessage();
                        continue;
                    }
                    $socketName = Manager::getNameBySocket($readSocket);
                    switch ($readSocket) {
                        case $this->serverSocketManager->getEntranceSocket():
                            // TODO:There are new client links
                            $name                             = $this->serverSocketManager->accept($readSocket);
                            $this->socketTypeMap[$socketName] = 'client';
                            if ($client = $this->serverSocketManager->getClientByName($name)) {
                                switch ($this->handshake($client)) {
                                    case null:
                                        break;
                                    case false:
                                        $this->serverSocketManager->removeClient($readSocket);
                                        break;
                                    case true:
                                        $this->onConnect($client);
                                        break;
                                }
                            }
                            break;
                        case $this->dispatcherServer:
                            $this->handlerDispatcherMessage();
                            // TODO:A notification is sent from the scheduler
                            break;
                        default:
                            // TODO:Client messages
                            if ($client = $this->serverSocketManager->getClientBySocket($readSocket)) {
                                if (true === $client->verify) {
                                    if ($context = $client->getPlaintext()) {
                                        $this->onMessage($context, $client);
                                    } else {
                                        $this->onClose($client);
                                        $this->serverSocketManager->removeClient($readSocket);
                                    }
                                } else {
                                    $this->handshake($client);
                                }
                            }
                    }
                }
            } else {
                if (isset($this->serverSocketManager)) {
                    $this->serverSocketManager->handleBufferContext();
                }

                if ($this->dispatcherServerAisle->getCacheLength() > 0) {
                    $this->dispatcherServerAisle->write("");
                }
                $this->heartbeat();
                gc_collect_cycles();
            }
        }
    }

    /**
     * error handler
     *
     * @return void
     */
    private function registerErrorHandler(): void
    {
        return;
    }

    /**
     * @return bool
     */
    public function reConnectDispatcher(): bool
    {
        try {
            $this->dispatcherServer      = SocketUnix::connect(Dispatcher::$handleServiceUnixAddress);
            $this->dispatcherServerAisle = SocketAisle::create($this->dispatcherServer);
            $this->dispatcherServerAisle->setNoBlock();
            $this->noticeStart();
            return true;
        } catch (Exception $e) {
            Log::pdebug("[Subscribe]", $e->getMessage());
            return false;
        }
    }

    /**
     * notify the scheduler to start
     *
     * @return void
     */
    public function noticeStart(): void
    {
        $this->publishEvent(Service::PS_START, null);
        $this->initialize();
    }

    /**
     * publish an event
     *
     * @param string      $name    event name
     * @param mixed       $data    event data
     * @param string|null $message carry message
     * @return bool
     */
    public function publishEvent(string $name, mixed $data, string|null $message = null): bool
    {
        $event = new Event($this->publish, $name, $data);
        $build = new Build($this->publish, null, $event, $message);
        return $this->publish($build);
    }

    /**
     * publish a custom information package
     *
     * @param Build $package
     * @return bool
     */
    public function publish(Build $package): bool
    {
        return Dispatcher::AGREE::send($this->dispatcherServerAisle, (string)$package);
    }

    /**
     * create service
     *
     * @param string     $socketType
     * @param string     $address
     * @param int|null   $port
     * @param array|null $options
     * @return void
     */
    public function createServer(string $socketType, string $address, int|null $port = 0, array|null $options = []): void
    {
        $this->socketType    = $socketType;
        $this->serverAddress = $address;
        $this->serverPort    = $port;
        $this->socketOptions = $options;
        $this->isServer      = true;
    }

    /**
     * handle scheduler return message
     *
     * @return void
     */
    private function handlerDispatcherMessage(): void
    {
        $messageType = -9;
        try {
            $context = Dispatcher::AGREE::cutWithInt($this->dispatcherServerAisle, $messageType);
        } catch (Exception $exception) {
            do {
                Log::pdebug("[Server]", "Dispatcher is block,reconnect ...");
                sleep(1);
            } while (!$this->reConnectDispatcher());
            return;
        }
        switch ($messageType) {
            case Dispatcher::FORMAT_MESSAGE:
                $this->onMessage($context, $this->dispatcherServer);
                break;
            case Dispatcher::FORMAT_BUILD:
                $package = Build::unSerialize($context);
                $this->onPackage($package);
                break;
            case Dispatcher::FORMAT_EVENT:
                $event = unserialize($context);
                $this->builtEventHandle($event);
                $this->onEvent($event);
                break;
        }
    }

    /**
     * built in event handling
     *
     * @param Event $event
     * @return bool
     */

    public function builtEventHandle(Event $event): bool
    {
        switch ($event->getName()) {
            case Service::PC_CLOSE:
                $this->dispatcherServerAisle->release();
                $this->noticeClose();
                $this->release();
                exit;

            case Dispatcher::PE_DISPATCHER_CLOSE:
                $this->destroy();   // allow service release resource
                $this->release();   // release service info file
                exit;
            default:
                return false;
        }
    }

    /**
     * notify the scheduler to close
     *
     * @return bool
     */
    public function noticeClose(): bool
    {
        return $this->publishEvent(Service::PS_CLOSE, null);
    }

    /**
     * statement subscription
     *
     * @param string     $publisher subscribed publisher
     * @param string     $eventName subscribed events
     * @param int        $type      received message type
     * @param array|null $options
     * @return bool
     */
    public function subscribe(string $publisher, string $eventName, int $type, array|null $options = []): bool
    {
        $options = array_merge($options, [
            'publish' => $publisher,
            'event'   => $eventName,
            'type'    => $type,
        ]);
        return $this->publishEvent(Dispatcher::PD_SUBSCRIBE, $options);
    }

    /**
     * Unsubscribe
     *
     * @param string      $publisher canceled subscriber
     * @param string|null $eventName unsubscribed event
     * @return bool
     */
    public function unSubscribe(string $publisher, string|null $eventName = null): bool
    {
        return $this->publishEvent(Dispatcher::PD_SUBSCRIBE_UN, [
            'publish' => $publisher,
            'event'   => $eventName,
        ]);
    }
}
