<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Built\WebSocket;

use Cclilshy\PRipple\Communication\Socket\Client;
use function str_contains;

/**
 *
 */
class Accept
{
    /**
     * Attempts to recognize handshake data when receiving a client for the first time
     *
     * @param \Cclilshy\PRipple\Communication\Socket\Client $client
     * @return bool
     */
    const NEED_HEAD = [
        'Host'                  => true,
        'Upgrade'               => true,
        'Connection'            => true,
        'Sec-WebSocket-Key'     => true,
        'Sec-WebSocket-Version' => true
    ];

    public static function accept(Client $client): bool|null
    {
        $client->read($context);
        $buffer = $client->cache($context);
        switch ($identityInfo = self::verify($buffer)) {
            case null:
                return null;
            case false:
                return false;
            default:
                $client->info       = $identityInfo;
                $secWebSocketAccept = Accept::getSecWebSocketAccept($client->info['Sec-WebSocket-Key']);
                $client->write(Accept::generateResultContext($secWebSocketAccept));
                $client->cleanCache();
                return $client->handshake(\Cclilshy\PRipple\Communication\Agreement\WebSocket::class);
        }
    }

    /**
     * 验证信息
     *
     * @param string $buffer
     * @return array|false|null
     */
    public static function verify(string $buffer): array|false|null
    {
        if (str_contains($buffer, "\r\n\r\n")) {
            $verify = Accept::NEED_HEAD;
            $lines  = explode("\r\n", $buffer);
            $header = array();

            if (count($firstLineInfo = explode(" ", array_shift($lines))) !== 3) {
                return false;
            } else {
                $header['method']  = $firstLineInfo[0];
                $header['url']     = $firstLineInfo[1];
                $header['version'] = $firstLineInfo[2];
            }

            foreach ($lines as $line) {
                if ($_ = explode(":", $line)) {
                    $header[trim($_[0])] = trim($_[1] ?? '');
                    unset($verify[trim($_[0])]);
                }
            }

            if (count($verify) > 0) {
                return false;
            } else {
                return $header;
            }
        } else {
            return null;
        }
    }

    private static function getSecWebSocketAccept(string $key): string
    {
        return base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    }

    private static function generateResultContext(string $accept): string
    {
        $headers = [
            'Upgrade'              => 'websocket',
            'Connection'           => 'Upgrade',
            'Sec-WebSocket-Accept' => $accept
        ];
        $context = "HTTP/1.1 101 PRipple\r\n";
        foreach ($headers as $key => $value) {
            $context .= "{$key}: {$value} \r\n";
        }
        $context .= "\r\n";
        return $context;
    }
}