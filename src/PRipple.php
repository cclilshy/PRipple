<?php

namespace Cclilshy\PRipple;

use Cclilshy\PRipple\Service\ServiceStandard;

class PRipple
{
    private static array $services = [];

    public static function registerService(ServiceStandard $service): void
    {
        self::$services[get_class(self::$services)] = $service;
    }

    public static function go(): void
    {

    }
}