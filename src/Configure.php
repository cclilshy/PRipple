<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);

namespace Cclilshy\PRipple;

use Cclilshy\PRipple\Route\Route;


class Configure
{
    const NEED_EXTENDS = ['pcntl', 'sockets', 'posix', 'fileinfo'];

    /**
     * @return void
     */
    public static function init(): void
    {
        define('UL', '_');
        define('FS', DIRECTORY_SEPARATOR);
        define('BS', '\\');
        define('PRIPPLE_ROOT_PATH', realpath(__DIR__ . '/../'));
        define('PRIPPLE_APP_PATH', realpath(PRIPPLE_ROOT_PATH . '/app'));
        define('PRIPPLE_RUNTIME_PATH', PRIPPLE_ROOT_PATH . '/runtime');
        define('PRIPPLE_CONF_PATH', PRIPPLE_ROOT_PATH . '/config');
        define('PRIPPLE_PIPE_PATH', PRIPPLE_RUNTIME_PATH . '/pipe');
        define('PRIPPLE_CACHE_PATH', PRIPPLE_RUNTIME_PATH . '/cache');
        define('PRIPPLE_SOCK_PATH', PRIPPLE_RUNTIME_PATH . '/sock');
        define('PRIPPLE_LOG_PATH', PRIPPLE_RUNTIME_PATH . '/log');
        define('PRIPPLE_LANG_PATH', PRIPPLE_CONF_PATH . '/lang');
        include __DIR__ . '/Common.php';
        if (Configure::inspection()) {
            Route::init();
            Config::init();
            Log::init();
            PRipple::init();
        } else {
            die("environmental self test failed" . PHP_EOL);
        }
    }

    /**
     * run environment self test
     *
     * @return bool
     */
    public static function inspection(): bool
    {
        Configure::initPath(PRIPPLE_APP_PATH);
        Configure::initPath(PRIPPLE_CONF_PATH);
        Configure::initPath(PRIPPLE_RUNTIME_PATH);
        Configure::initPath(PRIPPLE_PIPE_PATH);
        Configure::initPath(PRIPPLE_CACHE_PATH);
        Configure::initPath(PRIPPLE_SOCK_PATH);
        Configure::initPath(PRIPPLE_LOG_PATH);
        Configure::initPath(PRIPPLE_LANG_PATH);
        foreach (Configure::NEED_EXTENDS as $extend) {
            if (!extension_loaded($extend)) {
                die("not extend : " . $extend . "\n");
            }
        }
        return true;
    }

    /**
     * @param string $path
     * @return void
     */
    public static function initPath(string $path): void
    {
        if (@!is_dir($path) && @!mkdir($path, 0744, true)) {
            die('create path ' . $path . ' failed' . PHP_EOL);
        }

        if (!is_readable($path) || !is_writable($path)) {
            die("the path does not have read and write permissions '{$path}' \n");
        }
    }
}