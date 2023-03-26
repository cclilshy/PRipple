<?php
namespace Cclilshy\PRipple;

class Configure
{
    public static function init(): void
    {
        define('UL', '_');
        define('FS', DIRECTORY_SEPARATOR);
        define('BS', '\\');
        define('RUNTIME_PATH', __DIR__ . FS . '../runtime');
        define('PIPE_PATH', RUNTIME_PATH . '/pipe');
        define('CACHE_PATH', RUNTIME_PATH . '/cache');
        define('SOCK_PATH', RUNTIME_PATH . '/sock');
    }
}