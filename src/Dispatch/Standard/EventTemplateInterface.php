<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-22 03:14:10
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Dispatch\Standard;

// 事件类型规范
interface EventTemplateInterface
{
    /**
     * 获取事件标识符
     *
     * @return string
     */
    public function getIdentifier(): string;


    /**
     * 设置事件标识符
     *
     * @param string $identifier
     * @return \Cclilshy\PRipple\Dispatch\Standard\EventTemplateInterface
     */
    public function setIdentifier(string $identifier): self;


    /**
     * 获取事件名称
     *
     * @return mixed
     */
    public function getName(): string;

    /**
     * 获取事件发生的时间戳
     *
     * @return mixed
     */
    public function getTimestamp(): int;


    /**
     * 获取事件数据
     *
     * @return mixed
     */
    public function getData(): mixed;


    /**
     * 将事件序列化
     *
     * @return mixed
     */
    public function serialize(): string;


    /**
     * 事件转化为数组
     *
     * @return mixed
     */
    public function toArray(): array;


    /**
     * 事件转化为json数据
     *
     * @return string
     */
    public function toJson(): string;

}