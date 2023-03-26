<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-24 12:51:01
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Dispatch;

use Cclilshy\PRipple\Console;

class SubscribeManager
{
    private array $subscribes;

    public function addSubscribes(string $publish, string $eventName, string $subscriber, int $option): void
    {
        $this->subscribes[$publish][$eventName][$subscriber] = $option;
        Console::debug("[Subscribe]订阅事件: {$subscriber} 订阅了 > {$publish} 的 `{$eventName}` 事件");
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
                Console::debug("取消订阅事件: {$subscriber} 取消订阅了 > {$publish} 的 `{$eventName}` 事件");
            }
        } else {
            foreach ($this->subscribes as $eventName => $_) {
                if (isset($this->subscribes[$publish][$eventName][$subscriber])) {
                    unset($this->subscribes[$publish][$eventName][$subscriber]);
                    Console::debug("取消订阅事件: {$subscriber} 取消订阅了 > {$publish} 的 `{$eventName}` 事件");
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
}