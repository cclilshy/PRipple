<?php

namespace Cclilshy\PRipple\Built\WebSocket;

use Cclilshy\PRipple\Communication\Socket\Client;

class Accept
{
    public static function accept(Client $client): bool
    {
        $client->read($context);
        $buffer = $client->cache($context);
        if ($identityInfo = self::verify($buffer)) {
            $client->info = (object)$identityInfo;
            $client->handshake();
        }
        return false;
    }

    /**
     * 验证信息
     *
     * @param string $buffer
     * @return array|false
     */
    public static function verify(string $buffer): array|false
    {
        return false;
    }
}