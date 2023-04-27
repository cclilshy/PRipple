<?php
declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-24 12:51:01
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Dispatch;

use Cclilshy\PRipple\Log;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;


class SubscribeManager
{
    private array $subscribes = [];

    /**
     * @param string     $publish
     * @param string     $eventName
     * @param string     $subscriber
     * @param array|null $options
     * @return void
     */
    public function addSubscribes(string $publish, string $eventName, string $subscriber, array|null $options = null): void
    {
        if ($options === null) {
            $options = ['type' => Dispatcher::FORMAT_EVENT];
        }
        $this->subscribes[$publish][$eventName][$subscriber] = $options;
        if (!isset($this->subscribes[$publish][$eventName]['count'])) {
            $this->subscribes[$publish][$eventName]['count'] = 0;
        }
        $msg = "[Subscribe]" . "{$subscriber} on subscribe  {$publish} {$eventName} event";
        Log::realTimeOutput($msg, true);
    }

    /**
     * remove a subscriber
     *
     * @param string $subscriber
     * @return void
     */
    public function unSubscriber(string $subscriber): void
    {
        foreach ($this->subscribes as $_publish => $_events) {
            foreach ($_events as $_eventName => $_needType) {
                $this->unSubscribes($subscriber, $_publish, $_eventName);
            }
        }
    }

    /**
     * Unsubscribe
     *
     * @param string      $subscriber Subscriber
     * @param string      $publish    Publisher
     * @param string|null $eventName  Case Name
     * @return void
     */
    public function unSubscribes(string $subscriber, string $publish, string|null $eventName = null): void
    {
        if ($eventName) {
            if (isset($this->subscribes[$publish][$eventName][$subscriber])) {
                unset($this->subscribes[$publish][$eventName][$subscriber]);
                $msg = "[Subscribe]" . "{$subscriber} un subscribe {$publish} {$eventName} event";
                Log::realTimeOutput($msg, true);
            }
        } else {
            foreach ($this->subscribes as $eventName => $_) {
                if (isset($this->subscribes[$publish][$eventName][$subscriber])) {
                    unset($this->subscribes[$publish][$eventName][$subscriber]);
                    $msg = ("[Subscribe]" . "{$subscriber} un subscribe {$publish} `{$eventName}` event");
                    Log::realTimeOutput($msg, true);
                }
            }
        }
    }

    /**
     * Get list of all subscribers by publisher and event
     *
     * @param string $publish
     * @param string $event
     * @return array
     */
    public function getSubscribesByPublishAndEvent(string $publish, string $event): array
    {
        return $this->subscribes[$publish][$event] ?? [];
    }

    /**
     * @return array
     */
    public function getSubscribes(): array
    {
        return $this->subscribes ?? [];
    }

    /**
     * @param Event $event
     * @return void
     */
    public function recordHappen(Event $event): void
    {
        $publish   = $event->getPublisher();
        $eventName = $event->getName();
        if (isset($this->subscribes[$publish][$eventName]['count'])) {
            $this->subscribes[$publish][$eventName]['count']++;
        } elseif (isset($this->subscribes[$publish]['DEFAULT']['count'])) {
            $this->subscribes[$publish]['DEFAULT']['count']++;
        }
    }
}
