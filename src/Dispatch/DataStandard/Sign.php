<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-27 13:07:08
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Dispatch\DataStandard;


/**
 * 消息包中创建签名
 * 发布的消息包部分属性仅允许在创建时定义如：发布者，发布时的消息，发布时的事件
 * 因此后续拿到该包的处理器允许对包进行签名以储存数据（实例一个本对象）
 */
class Sign
{
    // 签名者
    public string $name;
    // 签名指定数据
    public mixed $info;
    // 该签名的计数器（同一个包被一个人对象签名两次只记录计数器不覆盖数据）
    // 暂时不知道有什么用
    public int $count;

    public function __construct(string $name, mixed $info)
    {
        $this->name  = $name;
        $this->info  = $info;
        $this->count = 0;
    }

    /**
     * @param string $name @ 签名者
     * @param mixed  $info @ 储存数据
     * @return static
     */
    public static function sign(string $name, mixed $info): self
    {
        return new self($name, $info);
    }

    /**
     * 对原有的签名对象计数+1
     *
     * @return void
     */
    public function counter(): void
    {
        $this->count++;
    }

}