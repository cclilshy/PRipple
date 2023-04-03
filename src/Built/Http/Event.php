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
use Fiber;

class Event
{
    public array $accessList;

    public function access(Request $request) : void
    {
        $this->accessList[$request->getHash()] = new Fiber(function () use ($request) {
            $response = $request->toResponse();
        });
    }
}