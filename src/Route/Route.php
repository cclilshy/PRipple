<?php
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
 * @method static static (string $path)
 */
class Route
{
    public const METHODS = ['get', 'post', 'put', 'patch', 'delete', 'options', 'console', 'cron', 'static'];
    private static array $map = [];

    /**
     * 加载所有路由文件
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
     * 在允许的方法内定义路由
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
     * 组合方法定义路由
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
     * 模拟访问执行
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
     * 根据入口匹配路由Map
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
        // 遍历$depth数组，逐级访问$target中的键
        if ($method === 'STATIC' && isset(self::$map['STATIC'][$depth[0]])) {
            return self::$map['STATIC'][$depth[0]];
        }
        foreach ($depth as $key) {
            if (isset($target[$key])) {
                $target = &$target[$key];
            } else {
                // 如果当前键不存在，直接返回null
                return null;
            }
        }
        // 返回找到的映射，或者null（如果没有找到）
        return $target instanceof Map ? $target : null;
    }

    /**
     * 获取所有Console路由
     *
     * @return array
     */
    public static function getConsoles(): array
    {
        return self::$map['CONSOLE'] ?? [];
    }
}
