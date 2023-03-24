<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-22 03:45:56
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Dispatch\Standard;

abstract class EventTemplateAbstract implements EventTemplateInterface
{
    // 标识符，可以在创建事件时初始化否则自动生成
    protected string $identifier;
    // 事件名称
    protected string $name;
    // 发生时间
    protected int $timestamp;
    // 事件夹带数据
    protected mixed $data;

    /**
     * @param string   $name      @ 事件名称
     * @param mixed    $data      @ 数据
     * @param int|null $timestamp @ 发生时间
     */
    public function __construct(string $name, mixed $data, ?int $timestamp = null)
    {
        $this->identifier = md5(microtime(true) . mt_rand(1, 9999));
        $this->name       = $name;
        $this->data       = $data;
        $this->timestamp  = $timestamp ?? time();
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
     * @return \Cclilshy\PRipple\Dispatch\Standard\EventTemplateInterface
     */
    public function setIdentifier(string $identifier): EventTemplateInterface
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
