<?php

namespace Cclilshy\PRipple;

class Configure
{
    public static function init(): void
    {
        define('UL', '_');
        define('FS', DIRECTORY_SEPARATOR);
        define('BS', '\\');
        define('PIPE_PATH', __DIR__ . FS . '../pipe');
    }
}