<?php

namespace Cclilshy\PRipple\Dispatch\Standard;
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-21 20:32:14
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

use Cclilshy\PRipple\Dispatch\Build;

// 事件处理器标准
interface ServiceInterface
{
    public function execMessage(string $message): void;

    public function execPackage(Build $package): void;

    public function execEvent(EventTemplateAbstract $event): void;

    public function execOriginalContext(string $context): void;
}