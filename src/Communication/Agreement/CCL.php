<?php

declare(strict_types=1);
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-16 21:12:25
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: CCPHP
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Communication\Agreement;

use stdClass;
use Exception;
use Cclilshy\PRipple\Communication\Standard\AisleInterface;
use Cclilshy\PRipple\Communication\Standard\AgreementInterface;

/**
 * CCL协议初构
 * 经过测试可以使用
 */
class CCL implements AgreementInterface
{
    /**
     * 通过接口发送
     *
     * @param AisleInterface $aisle
     * @param string         $context
     * @return bool
     */
    public static function send(AisleInterface $aisle, string $context): bool
    {
        $context = self::build($context);
        return self::sendOriginal($aisle, $context);
    }

    /**
     * 报文打包
     *
     * @param string         $context  报文具体
     * @param \stdClass|null $standard 附加参数....
     * @return string 包
     */
    public static function build(string $context, stdClass|null $standard = null): string
    {
        $contextLength = strlen($context);
        $pack          = pack('L', $contextLength);
        return strlen($pack . $context) . '#' . $pack . $context;
        // 报文长度#正文长度PACK正文
    }

    private static function sendOriginal(AisleInterface $aisle, string $context): bool
    {
        $fullContextLength = strlen($context);
        do {
            $writeResult = $aisle->write($context, $length);
            if ($length !== false && $length > 0) {
                $fullContextLength -= $length;
            }
        } while ($writeResult && $length > 0 && $fullContextLength > 0);
        return $fullContextLength === 0;
    }

    /**
     * 发送一条附加一个字节的整数的信息,最长4个字节
     *
     * @param \Cclilshy\PRipple\Communication\Standard\AisleInterface $aisle
     * @param string                                                  $context
     * @param int                                                     $int
     * @return bool
     */
    public static function sendWithInt(AisleInterface $aisle, string $context, int $int): bool
    {
        $pack    = pack('L', $int);
        $context = $pack . $context;
        $build   = self::build($context);
        return self::sendOriginal($aisle, $build);
    }

    public static function sendWithString(AisleInterface $aisle, string $context, string $param): bool
    {
        $pack    = pack('A64', $param);
        $context = $pack . $context;
        $build   = self::build($context);
        return self::sendOriginal($aisle, $build);
    }

    /**
     * 报文验证
     *
     * @param string         $context  报文
     * @param \stdClass|null $standard 附加参数
     * @return string|false 验证结果
     */
    public static function verify(string $context, ?stdClass $standard): string|false
    {
        //不支持校验
        return false;
    }

    /**
     * 切断一条带整形参数的报文
     *
     * @param \Cclilshy\PRipple\Communication\Standard\AisleInterface $aisle
     * @param                                                         $int
     * @return string|false
     * @throws \Exception
     */
    public static function cutWithInt(AisleInterface $aisle, &$int): string|false
    {
        if ($context = self::cut($aisle)) {
            if ($intPack = substr($context, 0, 4)) {
                if ($pack = unpack('L', $intPack)) {
                    $int = $pack[1];
                }
            }
            return $context;
        }
        return false;
    }

    /**
     * 报文切片
     *
     * @param AisleInterface $aisle 任意通道
     * @return string|false 切片结果
     * @throws \Exception
     */
    public static function cut(AisleInterface $aisle): string|false
    {
        $length = '';
        do {
            if (!$aisle->read($symbol, 1)) {
                throw new Exception("[CCL]无法解析空报文");
            }
            if ($symbol === '#') {
                break;
            } else {
                $length .= $symbol;
            }
        } while (true);
        $contextLengthFull = intval($length);
        $contextLengthHead = 4;
        $contextLengthBody = $contextLengthFull - 4;

        if (!$aisle->read($pack, $contextLengthHead)) {
            return false;
        } elseif (!$contextHeadVerify = unpack('L', $pack)) {
            return false;
        } else {
            $originalContextLength = $contextHeadVerify[1];
        }
        if ($contextBody = $aisle->read($context, $contextLengthBody)) {
            if ($originalContextLength === strlen($context)) {
                return $context;
            } else {
                throw new Exception("[CCL]该管道报文不完整");
            }
        } else {
            return false;
        }
    }

    /**
     * @throws \Exception
     */
    public static function cutWithString(AisleInterface $aisle, &$string): string|false
    {
        if ($context = self::cut($aisle)) {
            if ($intPack = substr($context, 0, 64)) {
                if ($pack = unpack('A64', $intPack)) {
                    $string = $pack[1];
                }
            }
            return $context;
        }
        return false;
    }

    /**
     * 不支持调整
     *
     * @param \Cclilshy\PRipple\Communication\Standard\AisleInterface $aisle
     * @return string|false
     */
    public static function corrective(AisleInterface $aisle): string|false
    {
        return false;
    }
}
