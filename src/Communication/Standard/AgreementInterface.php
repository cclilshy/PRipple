<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Communication\Standard;

/**
 * 消息传输规范
 */

/**
 * Agreement 不是一个具体
 * 需要支持报文打包，解析，验证，清理等
 * 这个接口的实现必须考虑在多个方法中适用
 * 每个Agreement的实现都有不同的特征
 */

use stdClass;

/**
 *
 */
interface AgreementInterface
{

    /**
     * 报文打包
     *
     * @param string $context 报文具体
     * @return string 包
     */
    public static function build(string $context): string;


    /**
     * 通过协议发送
     *
     * @param CommunicationInterface $aisle
     * @param string                 $context
     * @return bool
     */
    public static function send(CommunicationInterface $aisle, string $context): bool;


    /**
     * 报文验证
     *
     * @param string         $context  报文
     * @param \stdClass|null $Standard 附加参数
     * @return string|false 验证结果
     */
    public static function verify(string $context, ?stdClass $Standard): string|false;


    /**
     * 报文切片
     *
     * @param CommunicationInterface $aisle 任意通道
     * @return string|false 切片结果
     */
    public static function cut(CommunicationInterface $aisle): string|false;


    /**
     * 抛弃脏数据，调整通道指针
     *
     * @param \Cclilshy\PRipple\Communication\Standard\CommunicationInterface $aisle
     * @return string|false
     */
    public static function corrective(CommunicationInterface $aisle): string|false;


}