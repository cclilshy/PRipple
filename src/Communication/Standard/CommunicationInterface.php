<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-16 20:28:40
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */
declare(strict_types=1);

namespace Cclilshy\PRipple\Communication\Standard;

interface CommunicationInterface
{
    const EXT = '.switch';

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
     * @return int|bool
     */
    public function write(string $context): int|bool;
}