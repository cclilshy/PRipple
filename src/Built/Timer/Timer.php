<?php
namespace Cclilshy\PRipple\Built\Timer;

use SplPriorityQueue;
use Cclilshy\PRipple\Service\Service;
use Cclilshy\PRipple\Dispatch\Dispatcher;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;
use Cclilshy\PRipple\Communication\Socket\Client;

class Timer extends Service
{
    // 使用 SplPriorityQueue 保存任务队列
    private SplPriorityQueue $taskQueue;

    public function __construct()
    {
        parent::__construct('Timer');
        $this->taskQueue = new SplPriorityQueue();
    }

    public function initialize(): void
    {
        $this->subscribe('HttpService', 'sleep', Dispatcher::FORMAT_EVENT);
    }

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

    public function onConnect(Client $client): void
    {

    }

    public function onClose(Client $client): void
    {

    }

    public function onEvent(Event $event): void
    {
        if ($event->getName() == 'sleep') {
            $sleepData = $event->getData();
            $sleepTime = $sleepData['time'];

            // 将任务添加到队列中
            $this->taskQueue->insert(['time' => time() + $sleepTime, 'event' => $event], -time() - $sleepTime);
        }
    }

    public function onMessage(string $context, Client $client): void
    {

    }

    public function onPackage(\Cclilshy\PRipple\Dispatch\DataStandard\Build $package): void
    {

    }
}
