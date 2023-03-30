<?php

namespace Cclilshy\PRipple\Built\Http;

use Cclilshy\PRipple\Route\Route;

class Http
{
    const ROOT_PATH           = PRIPPLE_ROOT_PATH . '/app/Http';
    const PUBLIC_PATH         = Http::ROOT_PATH . '/public';
    const TEMPLATE_PATH       = Http::ROOT_PATH . '/template';
    const ROUTE_PATH          = Http::ROOT_PATH . '/route';
    const BUILT_ROUTE_PATH    = __DIR__ . '/.built/route';
    const BUILT_TEMPLATE_PATH = __DIR__ . '/.built/template';

    public static function getBuiltTemplate(string $name): string
    {
        if (file_exists(Http::BUILT_TEMPLATE_PATH . "/{$name}.tpl")) {
            return file_get_contents(Http::BUILT_TEMPLATE_PATH . "/{$name}.tpl");
        }
        return '';
    }

    public static function init(): void
    {
        Route::loadPath(Http::BUILT_ROUTE_PATH);
        Route::loadPath(Http::ROUTE_PATH);
    }
}