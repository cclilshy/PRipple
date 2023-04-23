<?php
declare(strict_types=1);
/*
 * @Author: cclilshy cclilshy@163.com
 * @Date: 2022-12-04 00:13:15
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: PRipple
 * Copyright (c) 2022 by cclilshy email: cclilshy@163.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Http\Text;

use Cclilshy\PRipple\Config;
use Cclilshy\PRipple\Statistics;
use Cclilshy\PRipple\Built\Http\Http;
use Cclilshy\PRipple\Built\Http\Request;

// Load The Running Request Information
// And Guide To The Destination According To The Routing Static Method Can Be Called Anywhere


/**
 *
 */
class Text
{
    /**
     * 响应模板插入调试面板
     *
     * @param string                               $content
     * @param \Cclilshy\PRipple\Built\Http\Request $request
     * @param \Cclilshy\PRipple\Statistics         $statistics
     * @return string
     */
    public static function statistics(string $content, Request $request, Statistics $statistics): string
    {
        if ($request->isAjax() === false) {
            $statistics->record('endTime', microtime(true));
            $general = [
                'timeLength' => $statistics->endTime - $statistics->startTime,
                'uri'        => $request->path,
                'fileCount'  => count($statistics->loadFiles),
                'memory'     => $statistics->memory,
                'maxMemory'  => $statistics->maxMemory
            ];
            $plaster = new Plaster();
            $plaster->assign('sqlps', $statistics->sqlps);
            $plaster->assign('files', $statistics->loadFiles);
            $plaster->assign('general', $general);
            $plaster->assign('gets', $request->get());
            $plaster->assign('posts', $request->post());
            $statisticsHtml = Http::getBuiltTemplate('statistics');
            $content .= PHP_EOL . $plaster->apply($statisticsHtml);
        }
        return $content;
    }

    /**
     * 由错误模板接手请求
     *
     * @param int                                  $errno
     * @param string                               $erst
     * @param string                               $errFile
     * @param int                                  $errLine
     * @param \Cclilshy\PRipple\Built\Http\Request $request
     * @param \Cclilshy\PRipple\Statistics         $statistics
     * @return string|null
     */
    public static function htmlErrorPage(int $errno, string $erst, string $errFile, int $errLine, Request $request, Statistics $statistics): ?string
    {
        $statistics->record('endTime', microtime(true));
        $fileDescribe = '';
        if ($errLines = file($errFile)) {
            $startLine = max($errLine - 10, 1);
            for ($i = 0; $i < 21; $i++, $startLine++) {
                if ($startLine > count($errLines)) {
                    break;
                }

                $fileDescribe .= $errLines[$startLine - 1];
            }
        }

        $general = [
            'uri'        => $request->path,
            'info'       => [
                'errno'        => $errno,
                'error'        => $erst,
                'errFile'      => $errFile,
                'errLine'      => $errLine,
                'fileDescribe' => $fileDescribe
            ],
            'timeLength' => $statistics->endTime - $statistics->startTime,
            'fileCount'  => count($statistics->loadFiles),
            'memory'     => $statistics->memory,
            'maxMemory'  => $statistics->maxMemory
        ];

        $plaster = new Plaster();
        $plaster->assign('sqlps', $statistics->sqlps);
        $plaster->assign('files', $statistics->loadFiles);
        $plaster->assign('general', $general);
        $plaster->assign('gets', []);
        $plaster->assign('posts', []);
        $plaster->assign('config', Config::all());

        $html = Http::getBuiltTemplate('error');
        return $plaster->apply($html);
    }
}
