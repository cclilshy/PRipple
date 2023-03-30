<?php
declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-15 20:43:00
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: PRipple
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Http;

use Cclilshy\PRipple\Config;
use Cclilshy\PRipple\Statistics;
use Cclilshy\PRipple\Route\Route;
use Cclilshy\PRipple\Built\Http\Text\Text;
use Cclilshy\PRipple\Communication\Socket\Client;

class Event
{
    private static array $config;
    private Statistics   $statistics;
    private Request      $request;
    private Response     $response;
    private Client $client;

    /**
     * @param \Cclilshy\PRipple\Built\Http\Request          $request
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     */
    public function __construct(Request $request,Client $client)
    {
        $this->statistics = new Statistics();
        $this->client = $client;
        $this->request    = $request;
        $this->response   = new Response($request);
        $this->response->setName($this->request->getName());
    }

        /**
     * @param $name
     * @return mixed
     */
    public function __get($name): mixed
    {
        return $this->$name;
    }

    /**
     * @return static|null
     */
    public static function init(): self|null
    {
        self::$config = Config::get('http');
        return null;
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public static function config($key): mixed
    {
        return self::$config[$key] ?? null;
    }
}