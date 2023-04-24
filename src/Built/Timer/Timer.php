<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Built\Timer;

use SplPriorityQueue;
use Cclilshy\PRipple\Service\Service;
use Cclilshy\PRipple\Dispatch\Dispatcher;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Communication\Socket\Client;
use Cclilshy\PRipple\Dispatch\DataStandard\Build;

/**
 *
 */
class Timer extends Service
{
    // 使用 SplPriorityQueue 保存任务队列
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
        $this->subscribe('HttpService', 'sleep', Dispatcher::FORMAT_EVENT);
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        $now = time();
        while (!$this->taskQueue->isEmpty()) {
            $task = $this->taskQueue->top();
            if ($task['time'] <= $now) {
                $this->publishEvent($task['event']->getData()['name'], null);
                $this->taskQueue->extract();
            } else {
                break;
            }
        }
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
     * @return bool|null
     */
    public function handshake(Client $client): bool|null
    {
        return $client->handshake();
    }

    /**
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function onClose(Client $client): void
    {

    }

    /**
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Event $event
     * @return void
     */
    public function onEvent(Event $event): void
    {
        if ($event->getName() == 'sleep') {
            $sleepData = $event->getData();
            $sleepTime = $sleepData['time'];

            // 将任务添加到队列中
            $this->taskQueue->insert(['time' => time() + $sleepTime, 'event' => $event], -time() - $sleepTime);
        }
    }

    /**
     * @param string                                        $context
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return void
     */
    public function onMessage(string $context, Client $client): void
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
