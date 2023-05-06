<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);

namespace Cclilshy\PRipple\Dispatch\DataStandard;


class Event
{
    protected string $publisher;
    // 标识符，可以在创建事件时初始化否则自动生成
    protected string $identifier;
    // 事件名称
    protected string $name;
    // 发生时间
    protected int $timestamp;
    // 事件夹带数据
    protected mixed $data;

    /**
     * @param string   $publisher @ 发布者
     * @param string   $name      @ 事件名称
     * @param mixed    $data      @ 数据
     * @param int|null $timestamp @ 发生时间
     */
    public function __construct(string $publisher, string $name, mixed $data, ?int $timestamp = null)
    {
        $this->publisher  = $publisher;
        $this->identifier = getRandHash();
        $this->name       = $name;
        $this->data       = $data;
        $this->timestamp  = $timestamp ?? time();
    }

    /**
     * @param string $context
     * @return false|self
     */
    public static function unSerialize(string $context): self|false
    {
        return unserialize($context);
    }

    /**
     * @return string
     */
    public function getPublisher(): string
    {
        return $this->publisher;
    }

    /**
     * 事件反序化
     *
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this);
    }

    /**
     * 事件名称
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取事件时间
     *
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * 获取事件数据
     *
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * 获取事件标识
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * 设置身份标识符
     *
     * @param string $identifier @标识符
     * @return \Cclilshy\PRipple\Dispatch\DataStandard\Event
     */
    public function setIdentifier(string $identifier): Event
    {
        $this->identifier = $identifier;
        return $this;
    }

    /**
     * 事件格式化为json
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * 事件格式化为数组
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'name'       => $this->name,
            'timestamp'  => $this->timestamp,
            'data'       => $this->data
        ];
    }
}
