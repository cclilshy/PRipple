<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-16 20:28:40
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */
declare(strict_types=1);

namespace Cclilshy\PRipple\Communication\Standard;

/**
 * Aisle 不是一个具体, 这个接口的实现必须考虑在多个方法中适用
 * 如协议发送通用接口
 * 每个Aisle的实现都有不同的特征, 一对一/一对多/堵塞/非堵塞/内存缓冲/文件实体传输管道
 */
interface AisleInterface
{
    public const EXT = '.aisle';

    /**
     * 创建通道
     *
     * @param mixed $base
     * @return self|false
     */
    public static function create(mixed $base): self|false;

    /**
     * 连接通道
     *
     * @param string $name
     * @return false|static
     */
    public static function link(string $name): self|false;

    /**
     * 读取数据
     *
     * @param string   $data
     * @param int|null $length
     * @return bool
     */
    public function read(mixed &$data, int|null $length = null): bool;

    /**
     * 向通道内写数据
     *
     * @param string $context
     * @param        $handledLength
     * @return bool
     */
    public function write(string $context, &$handledLength): bool;
}