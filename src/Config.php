<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);

namespace Cclilshy\PRipple;

use stdClass;


class Config
{
    protected static array $config = [];

    /**
     * @return void
     */
    public static function init(): void
    {
        $files = scandir(PRIPPLE_CONF_PATH);
        foreach ($files as $item) {
            if ($item === '.' || $item === '..')
                continue;
            $fullPath = PRIPPLE_CONF_PATH . FS . $item;
            if (is_dir($fullPath)) {
                continue;
            } elseif (is_file($fullPath) && pathinfo($fullPath)['extension'] === 'php') {
                self::$config[pathinfo($item)['filename']] = require PRIPPLE_CONF_PATH . FS . $item;
            }
        }
    }

    /**
     * @param string $name
     * @return \stdClass|null
     */
    public static function std(string $name): stdClass|null
    {
        return self::get($name) ? (object)self::get($name) : null;
    }


    /**
     * @param string $name
     * @return array|mixed|null
     */
    public static function get(string $name): mixed
    {
        $reqConstruct = explode('.', $name);
        $rest         = self::$config;
        for ($i = 0; $i < count($reqConstruct); $i++) {
            $rest = $rest[$reqConstruct[$i]] ?? null;
        }
        return $rest;
    }


    /**
     * @param string $name
     * @param        $value
     * @return mixed
     */
    public static function set(string $name, $value): mixed
    {
        $reqConstruct = explode('.', $name);
        $rest         = &self::$config;
        for ($i = 0; $i < count($reqConstruct); $i++) {
            $rest = &$rest[$reqConstruct[$i]];
        }
        $rest = $value;
        return $value;
    }

    /**
     * @return array
     */
    public static function all(): array
    {
        return self::$config;
    }

    /**
     * @param string $name
     * @return void
     */
    public static function env(string $name): void
    {

    }
}