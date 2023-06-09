<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);
namespace Cclilshy\PRipple\Built\Timer;

use SplPriorityQueue;
use Cclilshy\PRipple\Service\Service;
use Cclilshy\PRipple\Dispatch\Dispatcher;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Communication\Socket\Client;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;


class Timer extends Service
{
    // Use SplPriorityQueue to hold the task queue
    private SplPriorityQueue $taskQueue;

    public function __construct()
    {
        parent::__construct('Timer');
        $this->taskQueue = new SplPriorityQueue();
    }

    /**
     * @return void
     */
    public function initialize(): void
    {
        $this->subscribe('HttpService', 'ControllerSleep', Dispatcher::FORMAT_EVENT);
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        $now = time();
        while (!$this->taskQueue->isEmpty()) {
            $task = $this->taskQueue->top();
            if ($task['time'] <= $now && $event = $task['event']) {
                $this->publishEvent($event->getName(), $event->getData());
                $this->taskQueue->extract();
            } else {
                break;
            }
        }
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
     * @return bool|null
     */
    public function handshake(Client $client): bool|null
    {
        return $client->handshake();
    }

    /**
     * @param Client $client
     * @return void
     */
    public function onClose(Client $client): void
    {

    }

    /**
     * @param Event $event
     * @return void
     */
    public function onEvent(Event $event): void
    {
        if ($event->getName() == 'ControllerSleep') {
            $sleepData = $event->getData();
            $sleepTime = $sleepData['time'];
            // Add task to queue
            $this->taskQueue->insert([
                'time'  => time() + $sleepTime,
                'event' => $event
            ], -time() - $sleepTime);
        }
    }

    /**
     * @param string $context
     * @param Client $client
     * @return void
     */
    public function onMessage(string $context, Client $client): void
    {

    }

    /**
     * @param Build $package
     * @return void
     */
    public function onPackage(Build $package): void
    {

    }

    public function destroy(): void
    {

    }
}
