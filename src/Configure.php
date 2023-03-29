<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-27 13:07:08
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple;

class Configure
{
    public static function init(): void
    {
        define('UL', '_');
        define('FS', DIRECTORY_SEPARATOR);
        define('BS', '\\');
        define('PRIPPLE_RUNTIME_PATH', __DIR__ . FS . '../runtime');
        define('PRIPPLE_PIPE_PATH', PRIPPLE_RUNTIME_PATH . '/pipe');
        define('PRIPPLE_CACHE_PATH', PRIPPLE_RUNTIME_PATH . '/cache');
        define('PRIPPLE_SOCK_PATH', PRIPPLE_RUNTIME_PATH . '/sock');
    }
}