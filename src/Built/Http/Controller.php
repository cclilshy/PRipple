<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-14 23:57:02
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: PRipple
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Http;

use Fiber;
use Cclilshy\PRipple\Config;
use Cclilshy\PRipple\Statistics;
use Cclilshy\PRipple\Built\Http\Text\Text;
use Cclilshy\PRipple\Built\Http\Text\Plaster;
use Cclilshy\PRipple\Dispatch\DataStandard\Event;


class Controller
{
    protected Event      $http;
    protected Plaster    $plaster;
    protected Response   $response;
    protected Request    $request;
    protected Statistics $statistics;
    protected array      $assign = [];

    /**
     * @param Request $base
     */
    public function __construct(Request $base)
    {
        $this->request    = $base;
        $this->response   = $base->response;
        $this->statistics = $base->statistics;
        $this->plaster    = new Plaster();
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->display();
    }

    /**
     * @return string|false
     */
    public function display(): string|false
    {
        // TODO: Implement __toString() method.
        $templateInfo               = debug_backtrace()[2];
        $class                      = $templateInfo['class'];
        $function                   = $templateInfo['function'];
        $controllerName             = strtolower(substr($class, strrpos($class, '\\') + 1));
        $functionToTemplateFileName = strtolower(preg_replace('/([A-Z])/', '$0_', $function));
        $templatePath               = Http::TEMPLATE_PATH . FS . $controllerName . FS . $functionToTemplateFileName . '.' . Config::get('HttpService.template_extension');
        if (file_exists($templatePath)) {
            $template = file_get_contents($templatePath);
            $html     = $this->plaster->apply($template, $this->assign);
            if (Http::config('debug')) {
                $html = Text::statistics($html, $this->request, $this->statistics);
            }
            return $html;
        } else {
            return Text::htmlErrorPage(404, 'Template file not found:' . $templatePath, __FILE__, __LINE__, $this->request, $this->statistics);
        }
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public function assign(string $key, mixed $value): void
    {
        $this->assign[$key] = $value;
    }


    /**
     * @param array|string $data
     * @return string
     */
    public function json(array|string $data): string
    {
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $this->header('Content-Type', 'application/json');
        return $data;
    }

    /**
     * @param string $key
     * @param string $value
     * @return void
     */
    public function header(string $key, string $value): void
    {
        $this->response->setHeader($key, $value);
    }

    /**
     * @param int $time
     * @return void
     */
    public function sleep(int $time): void
    {
        if (Http::config('fiber')) {
            $event = new Event($this->request->getHash(), 'sleep', [
                'time' => $time,
            ]);
            Fiber::suspend($event);
        } else {
            sleep($time);
        }
    }
}
