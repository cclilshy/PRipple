<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */
declare(strict_types=1);
namespace Cclilshy\PRipple\Communication\Agreement;

use stdClass;
use Cclilshy\PRipple\Communication\Standard\AgreementInterface;
use Cclilshy\PRipple\Communication\Standard\CommunicationInterface;


class WebSocket implements AgreementInterface
{
    /**
     * 通过接口发送
     *
     * @param CommunicationInterface $aisle
     * @param string                 $context
     * @return bool
     */
    public static function send(CommunicationInterface $aisle, string $context): bool
    {
        $build = self::build($context);
        return (bool)$aisle->write($build);
    }


    /**
     * 报文打包
     *
     * @param string $context 报文具体
     * @return string 包
     */
    public static function build(string $context, int $opcode = 0x1, bool $fin = true): string
    {
        $frame      = chr(($fin ? 0x80 : 0) | $opcode); // FIN 和 Opcode
        $contextLen = strlen($context);
        if ($contextLen < 126) {
            $frame .= chr($contextLen); // Payload Length
        } elseif ($contextLen <= 0xFFFF) {
            $frame .= chr(126) . pack('n', $contextLen); // Payload Length 和 Extended payload length (2 字节)
        } else {
            $frame .= chr(127) . pack('J', $contextLen); // Payload Length 和 Extended payload length (8 字节)
        }
        $frame .= $context; // Payload Data
        return $frame;
    }

    /**
     * 报文验证
     *
     * @param string         $context  报文
     * @param \stdClass|null $Standard 附加参数
     * @return string|false 验证结果
     */
    public static function verify(string $context, ?stdClass $Standard): string|false
    {
        //不支持校验
        return false;
    }

    /**
     * 报文切片
     *
     * @param CommunicationInterface $aisle 任意通道
     * @return string|false 切片结果
     * @throws \Exception
     */
    public static function cut(CommunicationInterface $aisle): string|false
    {
        $aisle->read($context);
        return self::parse($context);
    }

    /**
     * @param string $context
     * @return string
     */
    public static function parse(string $context): string
    {
        $payload       = '';
        $payloadLength = '';
        $mask          = '';
        $maskingKey    = '';
        $opcode        = '';
        $fin           = '';
        $dataLength    = strlen($context);
        $index         = 0;

        $byte          = ord($context[$index++]);
        $fin           = ($byte & 0x80) != 0;
        $opcode        = $byte & 0x0F;
        $byte          = ord($context[$index++]);
        $mask          = ($byte & 0x80) != 0;
        $payloadLength = $byte & 0x7F;

        // 处理 2 字节或 8 字节的长度字段
        if ($payloadLength > 125) {
            if ($payloadLength == 126) {
                $payloadLength = unpack('n', substr($context, $index, 2))[1];
                $index         += 2;
            } else {
                $payloadLength = unpack('J', substr($context, $index, 8))[1];
                $index         += 8;
            }
        }

        // 处理掩码密钥
        if ($mask) {
            $maskingKey = substr($context, $index, 4);
            $index      += 4;
        }

        // 处理负载数据
        $payload = substr($context, $index);
        if ($mask) {
            for ($i = 0; $i < strlen($payload); $i++) {
                $payload[$i] = chr(ord($payload[$i]) ^ ord($maskingKey[$i % 4]));
            }
        }

        return $payload;
    }

    /**
     * 不支持调整
     *
     * @param CommunicationInterface $aisle
     * @return string|false
     */
    public static function corrective(CommunicationInterface $aisle): string|false
    {
        return false;
    }
}
