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
        define('PRIPPLE_ROOT_PATH', realpath(__DIR__ . FS . '../'));
        define('PRIPPLE_RUNTIME_PATH', PRIPPLE_ROOT_PATH . '/runtime');
        define('PRIPPLE_APP_PATH', PRIPPLE_ROOT_PATH . '/app');
        define('PRIPPLE_CONF_PATH', PRIPPLE_ROOT_PATH . '/config');
        define('PRIPPLE_PIPE_PATH', PRIPPLE_RUNTIME_PATH . '/pipe');
        define('PRIPPLE_CACHE_PATH', PRIPPLE_RUNTIME_PATH . '/cache');
        define('PRIPPLE_SOCK_PATH', PRIPPLE_RUNTIME_PATH . '/sock');
    }
}