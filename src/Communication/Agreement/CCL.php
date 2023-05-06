<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */
declare(strict_types=1);
namespace Cclilshy\PRipple\Communication\Agreement;

use stdClass;
use Exception;
use Cclilshy\PRipple\Communication\Standard\AgreementInterface;
use Cclilshy\PRipple\Communication\Standard\CommunicationInterface;


class CCL implements AgreementInterface
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
        $context = self::build($context);
        return self::sendRawContext($aisle, $context);
    }


    /**
     * 报文打包
     *
     * @param string $context 报文具体
     * @return string 包
     */
    public static function build(string $context): string
    {
        $contextLength = strlen($context);
        $pack          = pack('L', $contextLength);
        return strlen($pack . $context) . '#' . $pack . $context;
        // 报文长度#正文长度PACK正文
    }

    /**
     * @param CommunicationInterface $aisle
     * @param string                 $context
     * @return bool
     */
    private static function sendRawContext(CommunicationInterface $aisle, string $context): bool
    {
        return $aisle->write($context) !== false;
    }

    /**
     * 发送一条附加一个字节的整数的信息,最长4个字节
     *
     * @param CommunicationInterface $aisle
     * @param string                 $context
     * @param int                    $int
     * @return bool
     */
    public static function sendWithInt(CommunicationInterface $aisle, string $context, int $int): bool
    {
        $pack    = pack('L', $int);
        $context = $pack . $context;
        $package = self::build($context);
        return self::sendRawContext($aisle, $package);
    }

    /**
     * 发送一条附带一条文本的信息，最长64个字节
     *
     * @param CommunicationInterface $aisle
     * @param string                 $context
     * @param string                 $param
     * @return bool
     */
    public static function sendWithString(CommunicationInterface $aisle, string $context, string $param): bool
    {
        $pack    = pack('A64', $param);
        $context = $pack . $context;
        $package = self::build($context);
        return self::sendRawContext($aisle, $package);
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
     * 切断一条带整形参数的报文
     *
     * @param CommunicationInterface                                          $aisle
     * @param                                                                 $int
     * @return string|false
     * @throws \Exception
     */
    public static function cutWithInt(CommunicationInterface $aisle, &$int): string|false
    {
        if ($context = self::cut($aisle)) {
            if ($intPack = substr($context, 0, 4)) {
                if ($pack = unpack('L', $intPack)) {
                    $int = $pack[1];
                }
            }
            return substr($context, 4);
        }
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
        $length = '';
        do {
            if (!$aisle->read($symbol, 1)) {
                throw new Exception("[CCL]Context is empty");
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
    public static function cutWithString(CommunicationInterface $aisle, &$string): string|false
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
     * @param CommunicationInterface $aisle
     * @return string|false
     */
    public static function corrective(CommunicationInterface $aisle): string|false
    {
        return false;
    }

    public static function parse(string $context): string
    {

    }
}
