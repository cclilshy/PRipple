<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-21 13:19:11
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Communication\Standard;

interface PackageInterface
{
    public static function create(string $name): self;

    public function push(string $context): self|false;

    public function getStatusCode(): int;

    public function complete(): bool;
}
