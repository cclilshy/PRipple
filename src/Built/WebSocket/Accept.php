<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Built\WebSocket;

use Cclilshy\PRipple\Communication\Socket\Client;

/**
 *
 */
class Accept
{
    /**
     * 初次接收客户时尝试识别握手数据
     *
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return bool
     */
    public static function accept(Client $client): bool
    {
        $client->read($context);
        $buffer = $client->cache($context);
        if ($identityInfo = self::verify($buffer)) {
            $client->info = (object)$identityInfo;
            return $client->handshake();
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
        var_dump($buffer);
        return false;
    }
}