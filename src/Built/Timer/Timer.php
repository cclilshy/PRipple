<?php

namespace Cclilshy\PRipple\Built\Timer;


use Cclilshy\PRipple\Service\Service;
use Cclilshy\PRipple\Dispatch\Dispatcher;
use Cclilshy\PRipple\Communication\Socket\Client;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;

class Timer extends Service
{
    private array $tasks = array();

    public function __construct()
    {
        parent::__construct('Timer');
    }

    public function initialize(): void
    {
        $this->subscribe('HttpService', 'sleep', Dispatcher::FORMAT_EVENT);
    }

    public function heartbeat(): void
    {
        $time = time();
        if ($task = $this->tasks[$time] ?? null) {
            if ($task->getName() === 'sleep') {
                $this->publishEvent($task->getData()['name'], null);
            }
            unset($this->tasks[$time]);
        }
    }

    public function onConnect(Client $client): void
    {

    }

    public function onClose(Client $client): void
    {

    }

    public function onEvent(Event $event): void
    {
        if ($event->getName() == 'sleep') {
            $sleepData                        = $event->getData();
            $sleepTime                        = $sleepData['time'];
            $this->tasks[$sleepTime + time()] = $event;
        }
    }

    public function onMessage(string $context, Client $client): void
    {

    }

    public function onPackage(Build $package): void
    {

    }
}