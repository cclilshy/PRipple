<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);

namespace Cclilshy\PRipple;


class Lang
{
    private static Lang  $object;
    private static array $package;


    /**
     * @param string $langType
     * @return void
     */
    public static function init(string $langType): void
    {
        $packageFilePath = PRIPPLE_CONF_PATH . "/lang/{$langType}.php";
        self::$package   = is_file($packageFilePath) ? require $packageFilePath : [];
    }

    /**
     * @param string      $originContent
     * @param string|null $default
     * @return string|null
     */
    public function get(string $originContent, string|null $default = null): string|null
    {
        return self::$package[$originContent] ?? $default;
    }
}