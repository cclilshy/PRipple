<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);

namespace Cclilshy\PRipple\Built\Http\Text;

use Cclilshy\PRipple\Config;
use Cclilshy\PRipple\Statistics;
use Cclilshy\PRipple\Built\Http\Http;
use Cclilshy\PRipple\Built\Http\Request;

// Load The Running Request Information
// And Guide To The Destination According To The Routing Static Method Can Be Called Anywhere


class Text
{
    /**
     * Responsive templates inserted into the debug panel
     *
     * @param string     $content
     * @param Request    $request
     * @param Statistics $statistics
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
            $content        .= PHP_EOL . $plaster->apply($statisticsHtml);
        }
        return $content;
    }

    /**
     * The error template takes over the request
     *
     * @param int        $errno
     * @param string     $erst
     * @param string     $errFile
     * @param int        $errLine
     * @param Request    $request
     * @param Statistics $statistics
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
