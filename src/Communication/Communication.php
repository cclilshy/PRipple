<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Communication;

use Cclilshy\PRipple\Communication\Socket\SocketInet;
use Cclilshy\PRipple\Communication\Socket\SocketUnix;


class Communication
{
    public const INET = SocketInet::class;
    public const UNIX = SocketUnix::class;
}