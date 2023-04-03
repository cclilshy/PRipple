<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-24 12:51:01
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Dispatch;

use Cclilshy\PRipple\Console;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;

class SubscribeManager
{
    private array $subscribes = [];

    public function addSubscribes(string $publish, string $eventName, string $subscriber, array|null $options = null): void
    {
        if ($options === null) {
            $options = ['type' => Dispatcher::FORMAT_EVENT];
        }
        $this->subscribes[$publish][$eventName][$subscriber] = $options;
        if (!isset($this->subscribes[$publish][$eventName]['count'])) {
            $this->subscribes[$publish][$eventName]['count'] = 0;
        }
        $msg = "[Subscribe]" . "{$subscriber} 订阅了 > {$publish} 的 `{$eventName}` 事件";
        Console::debug($msg);
        Dispatcher::noticeControl($msg, true);
    }

    /**
     * 移除一个订阅者
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
     * 取消订阅
     *
     * @param string      $subscriber 订阅者
     * @param string      $publish    发布者
     * @param string|null $eventName  事件名称
     * @return void
     */
    public function unSubscribes(string $subscriber, string $publish, string|null $eventName = null): void
    {
        if ($eventName) {
            if (isset($this->subscribes[$publish][$eventName][$subscriber])) {
                unset($this->subscribes[$publish][$eventName][$subscriber]);
                $msg = "[Subscribe]" . "{$subscriber} 取消订阅了 > {$publish} 的 `{$eventName}` 事件";
                Console::debug($msg);
                Dispatcher::noticeControl($msg, true);
            }
        } else {
            foreach ($this->subscribes as $eventName => $_) {
                if (isset($this->subscribes[$publish][$eventName][$subscriber])) {
                    unset($this->subscribes[$publish][$eventName][$subscriber]);
                    $msg = ("[Subscribe]" . "{$subscriber} 取消订阅了 > {$publish} 的 `{$eventName}` 事件");
                    Console::debug($msg);
                    Dispatcher::noticeControl($msg, true);
                }
            }
        }
    }

    /**
     * 通过发布者和事件获取所有订阅者列表
     *
     * @param string $publish
     * @param string $event
     * @return array
     */
    public function getSubscribesByPublishAndEvent(string $publish, string $event): array
    {
        return $this->subscribes[$publish][$event] ?? [];
    }

    public function getSubscribes(): array
    {
        return $this->subscribes ?? [];
    }

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
