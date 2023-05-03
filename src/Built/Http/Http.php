<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);

namespace Cclilshy\PRipple\Built\Http;

use Cclilshy\PRipple\Config;
use Cclilshy\PRipple\Route\Route;

class Http
{
    public const ROOT_PATH           = PRIPPLE_ROOT_PATH . '/app/Http';
    public const PUBLIC_PATH         = Http::ROOT_PATH . '/public';
    public const TEMPLATE_PATH       = Http::ROOT_PATH . '/template';
    public const ROUTE_PATH          = Http::ROOT_PATH . '/route';
    public const BUILT_ROUTE_PATH    = __DIR__ . '/.built/route';
    public const BUILT_TEMPLATE_PATH = __DIR__ . '/.built/template';
    private static array $templateCache = array();
    private static array $config        = array();

    /**
     * @param string $name
     * @return string
     */
    public static function getBuiltTemplate(string $name): string
    {
        if ($template = Http::$templateCache[$name] ?? null) {
            return $template;
        } elseif (file_exists(Http::BUILT_TEMPLATE_PATH . "/{$name}.php")) {
            $template = file_get_contents(Http::BUILT_TEMPLATE_PATH . "/{$name}.php");
            if (Http::config('template_cache')) {
                Http::$templateCache[$name] = $template;
            }
            return $template;
        } else {
            return '';
        }
    }

    public static function config(string $key): mixed
    {
        return Http::$config[$key] ?? null;
    }

    /**
     * @return void
     */
    public static function init(): void
    {
        Route::loadPath(Http::BUILT_ROUTE_PATH);
        Route::loadPath(Http::ROUTE_PATH);
        Http::$config = Config::get('HttpService') ?? [];
    }
}