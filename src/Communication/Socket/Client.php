<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-23 12:42:40
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Communication\Socket;

use Cclilshy\PRipple\Communication\Aisle\SocketAisle;

class Client extends SocketAisle
{
    public function __construct(mixed $socket, Manager $manager)
    {
        parent::__construct($socket, $manager);
    }
}