<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Built\Http;

use Cclilshy\PRipple\Route\Route;

/**
 *
 */
class Http
{
    public const ROOT_PATH           = PRIPPLE_ROOT_PATH . '/app/Http';
    public const PUBLIC_PATH         = Http::ROOT_PATH . '/public';
    public const TEMPLATE_PATH       = Http::ROOT_PATH . '/template';
    public const ROUTE_PATH          = Http::ROOT_PATH . '/route';
    public const BUILT_ROUTE_PATH    = __DIR__ . '/.built/route';
    public const BUILT_TEMPLATE_PATH = __DIR__ . '/.built/template';

    /**
     * @param string $name
     * @return string
     */
    public static function getBuiltTemplate(string $name): string
    {
        if (file_exists(Http::BUILT_TEMPLATE_PATH . "/{$name}.tpl")) {
            return file_get_contents(Http::BUILT_TEMPLATE_PATH . "/{$name}.tpl");
        }
        return '';
    }

    /**
     * @return void
     */
    public static function init(): void
    {
        Route::loadPath(Http::BUILT_ROUTE_PATH);
        Route::loadPath(Http::ROUTE_PATH);
    }
}