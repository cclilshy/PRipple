<?php
declare(strict_types=1);

namespace Cclilshy\PRipple;

use Cclilshy\PRipple\Dispatch\Dispatcher;

/**
 *
 */
class Log
{
    private static mixed $logFile;

    /**
     * 初始化日志
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
     * 通知监控器
     *
     * @param string $content
     * @return void
     */
    public static function realTimeOutput(string $content): void
    {
        Dispatcher::print($content);
        Log::insert($content);
    }

    /**
     * 打印状态
     *
     * @param string $content
     * @return void
     */
    public static function print(string $content): void
    {
        Console::printn($content);
    }

    /**
     * 写入日志
     *
     * @param string $content
     * @return void
     */
    public static function insert(string $content): void
    {
        fwrite(Log::$logFile, $content . PHP_EOL);
    }
}