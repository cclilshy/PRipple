<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);
/*
 * @Author: cclilshy cclilshy@163.com
 * @Date: 2022-12-08 14:48:01
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: PRipple
 * Copyright (c) 2022 by cclilshy email: cclilshy@163.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Route;

/**
 * @method static console(string $string, string $string1)
 * @method static get(string $string, string $string1)
 * @method static post(string $string, string $string1)
 * @method static put(string $string, string $string1)
 * @method static patch(string $string, string $string1)
 * @method static delete(string $string, string $string1)
 * @method static options(string $string, string $string1)
 * @method static cron(string $string, string $string1)
 * @method static static (string $path, string $string1)
 * @method static service(string $string, string $string1)
 */
class Route
{
    public const METHODS = ['get', 'post', 'put', 'patch', 'delete', 'options', 'console', 'cron', 'static', 'service'];
    private static array $map = [];

    /**
     * load all routing files
     */
    public static function init(): void
    {
        $path = PRIPPLE_CONF_PATH . FS . 'route';
        Route::loadPath($path);
    }

    /**
     * @param string $path
     * @return void
     */
    public static function loadPath(string $path): void
    {
        if (is_dir($path)) {
            $list = scandir($path);
            array_shift($list);
            array_shift($list);
            foreach ($list as $item) {
                require $path . FS . $item;
            }
        }
    }

    /**
     * define routes within allowed methods
     *
     * @param $name
     * @param $arguments
     * @return bool
     */
    public static function __callStatic($name, $arguments): bool
    {
        if (!in_array($name, self::METHODS)) {
            return false;
        }
        $method   = strtoupper($name);
        $entrance = $arguments[0];
        $entrance = ltrim($entrance, '/');
        $depth    = explode('/', $entrance);
        $target   = &self::$map[$method];

        foreach ($depth as $key) {
            $target[$key] = null;
            $target       = &$target[$key];
        }

        if ($method === 'STATIC') {
            $target = new Map('Static', $arguments[1], '');
        } elseif (is_callable($arguments[1])) {
            $target = new Map('Closure', '', '', $arguments[1]);
        } else {
            $_      = explode('@', $arguments[1]);
            $target = new Map('Controller', $_[0], $_[1] ?? 'main');
        }
        return true;
    }

    /**
     * composite methods define routes
     *
     * @param $methods
     * @param $uri
     * @param $callback
     * @return void
     */
    public static function match($methods, $uri, $callback): void
    {
        foreach ($methods as $item) {
            self::$item($uri, $item, $callback);
        }
    }

    /**
     * simulated access execution
     *
     * @param $method
     * @param $entrance
     * @return void
     */
    public static function simulation($method, $entrance): void
    {
        $result = self::guide($method, $entrance);
        $result && $result->run();
    }

    /**
     * match the route map according to the entry
     *
     * @param string $method
     * @param string $entrance
     * @return Map|null
     */
    public static function guide(string $method, string $entrance): Map|null
    {
        $entrance = ltrim($entrance, '/');
        $method   = strtoupper($method);
        $depth    = explode('/', $entrance);
        $target   = &self::$map[$method];
        // Traverse the depth array, accessing the keys in Target step by step
        if ($method === 'STATIC' && isset(self::$map['STATIC'][$depth[0]])) {
            return self::$map['STATIC'][$depth[0]];
        }
        foreach ($depth as $key) {
            if (isset($target[$key])) {
                $target = &$target[$key];
            } else {
                // If the current key does not exist, null is returned directly
                return null;
            }
        }
        // Returns the map found, or null if not found
        return $target instanceof Map ? $target : null;
    }

    /**
     * get all console routes
     *
     * @return array
     */
    public static function getConsoles(): array
    {
        return self::$map['CONSOLE'] ?? [];
    }

    /**
     * get a list of all registered services
     *
     * @return array
     */
    public static function getServices(): array
    {
        return self::$map['SERVICE'] ?? [];
    }
}
