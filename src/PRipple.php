<?php
declare(strict_types=1);

namespace Cclilshy\PRipple;

use Cclilshy\PRipple\Service\ServiceStandard;

/**
 *
 */
class PRipple
{
    private static array $services = [];

    /**
     * @param \Cclilshy\PRipple\Service\ServiceStandard $service
     * @return void
     */
    public static function registerService(ServiceStandard $service): void
    {
        self::$services[get_class(self::$services)] = $service;
    }

    /**
     * @return void
     */
    public static function go(): void
    {

    }
}