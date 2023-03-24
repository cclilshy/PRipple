<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-22 03:15:55
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Dispatch\EventTemplate;

use Cclilshy\PRipple\Dispatch\standard\EventTemplateAbstract;

class CommonTemplate extends EventTemplateAbstract
{
    /**
     * @param string   $name      事件类型名称
     * @param mixed    $data      事件特定数据
     * @param int|null $timestamp 发生时间
     */
    public function __construct(string $name, mixed $data, ?int $timestamp = null)
    {
        parent::__construct($name, $data, $timestamp);
    }
}
