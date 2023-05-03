<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);

namespace Cclilshy\PRipple;

use Cclilshy\PRipple\Dispatch\Dispatcher;


class Log
{
    private static mixed $logFile;

    /**
     * initialization log
     *
     * @return void
     */
    public static function init(): void
    {
        $recordFileName = PRIPPLE_LOG_PATH . '/real.log';
        if (file_exists($recordFileName)) {
            $lastFileName = PRIPPLE_LOG_PATH . '/record_' . date("YmdHis", filectime($recordFileName)) . '.log';
            copy($recordFileName, $lastFileName);
        }
        Log::$logFile = fopen($recordFileName, 'w+');
    }

    /**
     * notification monitor
     *
     * @param string $content
     * @return void
     */
    public static function realTimeOutput(string $content): void
    {
        if (PRipple::config('debug')) {
            Dispatcher::print($content);
        }
        if (PRipple::config('record_log')) {
            Log::insert($content);
        }
    }

    /**
     * printing status
     *
     * @param string $content
     * @return void
     */
    public static function print(string $content): void
    {
        Console::printn($content);
    }

    /**
     * write log
     *
     * @param string $content
     * @return void
     */
    public static function insert(string $content): void
    {
        fwrite(Log::$logFile, $content . PHP_EOL);
    }

    public static function pdebug(...$args): void
    {
        call_user_func_array([Console::class, 'pdebug'], $args);
    }
}