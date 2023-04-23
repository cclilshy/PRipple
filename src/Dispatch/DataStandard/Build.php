<?php
declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-21 20:46:42
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Dispatch\DataStandard;

// 消息包
use Cclilshy\PRipple\Communication\Standard\CommunicationInterface;

/**
 *
 */
class Build
{
    public array                 $signatures = array();     // 签名
    private readonly string      $publisher;                // 发布者名称
    private readonly string|null $message;                  // 消息内容
    private readonly string|null $uuid;                     // 包UUID
    private readonly Event|null  $event;                    // 事件
    private readonly string|null $targetHandlerName;        // 包目的地

    /**
     * 创建一个消息包
     *
     * @param string                                             $publisher         @发布者
     * @param string|null                                        $targetHandlerName @指定人（暂留待定）
     * @param \Cclilshy\PRipple\Dispatch\DataStandard\Event|null $event             @事件包
     * @param string|null                                        $message           @消息
     */
    public function __construct(string $publisher, string|null $targetHandlerName = null, Event|null $event = null, string|null $message = null)
    {
        $this->publisher         = $publisher;
        $this->message           = $message;
        $this->event             = $event;
        $this->uuid              = md5(microtime(true) . mt_rand(1, 9999));
        $this->targetHandlerName = $targetHandlerName;
    }     // 签名列表

    /**
     * 通过协议切割获得包内容
     *
     * @param string                                                          $agreement @ 协议类
     * @param \Cclilshy\PRipple\Communication\Standard\CommunicationInterface $aisle     @ 通道实体
     * @return \Cclilshy\PRipple\Dispatch\DataStandard\Build|false
     */
    public static function getBuildByAgreement(string $agreement, CommunicationInterface $aisle): Build|false
    {
        if ($context = call_user_func([$agreement, 'cut'], $aisle)) {
            return self::unSerialize($context);
        } else {
            return false;
        }
    }

    /**
     * @param string $context
     * @return \Cclilshy\PRipple\Dispatch\DataStandard\Build|false
     */
    public static function unSerialize(string $context): Build|false
    {
        @$res = unserialize($context);
        return $res;
    }

    /**
     * @return string
     */
    public function serialize(): string
    {
        return $this->__toString();
    }

    /**
     * 传输前转换
     *
     * @return string
     */
    public function __toString(): string
    {
        return serialize($this);
    }

    /**
     * 获取发布者名称
     *
     * @return string
     */
    public function getPublisher(): string
    {
        return $this->publisher;
    }

    /**
     * 获取包消息
     *
     * @return string|null
     */
    public function getMessage(): string|null
    {
        return $this->message ?? null;
    }

    /**
     * 获取包UUID
     *
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }


    /**
     * @return string
     */
    public function getTargetHandlerName(): string
    {
        return $this->targetHandlerName;
    }


    /**
     * 获取事件
     *
     * @return Event|null
     */
    public function getEvent(): Event|null
    {
        return $this->event ?? null;
    }

    /**
     * 签名
     * 允许任何事件在此处签名
     *
     * @param string $name
     * @param mixed  $info
     * @return $this
     */
    public function signIn(string $name, mixed $info): self
    {
        $this->filter($info);
        if ($signer = $this->signatures[$name] ?? null) {
            $signer->counter();
        } else {
            $this->signatures[$name] = Sign::sign($name, $info);
        }
        return $this;
    }


    /**
     * 可能会引起异常的参数过滤，引用传递
     *
     * @param mixed $arguments 过滤的参数
     * @return void
     */
    private function filter(mixed &$arguments): void
    {
        if (is_resource($arguments) || is_callable($arguments)) {
            $arguments = (string)$arguments;
        } elseif (is_array($arguments)) {
            foreach ($arguments as &$item) {
                $this->filter($item);
            }
        }
    }
}
