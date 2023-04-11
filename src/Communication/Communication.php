<?php

namespace Cclilshy\PRipple\Communication;

use Cclilshy\PRipple\Communication\Socket\SocketInet;
use Cclilshy\PRipple\Communication\Socket\SocketUnix;

class Communication
{
    const INET = SocketInet::class;
    const UNIX = SocketUnix::class;
}