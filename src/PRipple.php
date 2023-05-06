<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);
namespace Cclilshy\PRipple;

use Cclilshy\PRipple\Service\ServiceInfo;
use Cclilshy\PRipple\Service\ServiceStandard;


class PRipple
{
    private static array $services;
    private static array $config;

    public static function init(): void
    {
        self::$config = Config::get('pripple');
    }

    /**
     * @param ServiceStandard $service
     * @return void
     */
    public static function registerService(ServiceStandard $service): void
    {
        self::$services[$service->name] = $service;
    }

    /**
     * @return void
     */
    public static function go(): void
    {
        if ($serverInfo = ServiceInfo::create('pripple')) {
            $info = array();
            foreach (self::$services as $service) {
                switch ($pid = pcntl_fork()) {
                    case 0:
                        $service->launch();
                        exit;
                    case -1:
                        break;
                    default:
                        $info[$service->name] = $pid;
                        break;
                }
            }
            $serverInfo->info($info);
            Log::print("pripple service start success.");
        } else {
            Log::print('pripple service is running.');
        }
    }

    public static function stop(): void
    {
        // TODO: The final step is to force a shutdown of servers that cannot automatically release resources
        if ($service = ServiceInfo::load('pripple')) {
            foreach ($service->info() as $serviceName => $pid) {
                if (posix_kill($pid, SIGKILL)) {
                    Log::pdebug("kill {$serviceName} pid {$pid} ed.");
                }
            }
            $service->release();
        }

    }

    public static function config($name): mixed
    {
        return self::$config[$name] ?? null;
    }
}