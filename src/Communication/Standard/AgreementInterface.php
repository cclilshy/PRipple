<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Communication\Standard;

use stdClass;

/**
 * 消息传输规范
 */

/**
 * Agreement 不是一个具体
 * 需要支持报文打包，解析，验证，清理等
 * 这个接口的实现必须考虑在多个方法中适用
 * 每个Agreement的实现都有不同的特征
 */
interface AgreementInterface
{
    /**
     * 报文打包
     *
     * @param string         $context  报文具体
     * @param \stdClass|null $standard 附加参数
     * @return string 包
     */
    public static function build(string $context, ?stdClass $standard = null): string;

    /**
     * 通过协议发送
     *
     * @param AisleInterface $aisle
     * @param string         $context
     * @return bool
     */
    public static function send(AisleInterface $aisle, string $context): bool;

    /**
     * 报文验证
     *
     * @param string         $context  报文
     * @param \stdClass|null $standard 附加参数
     * @return string|false 验证结果
     */
    public static function verify(string $context, ?stdClass $standard): string|false;

    /**
     * 报文切片
     *
     * @param AisleInterface $aisle 任意通道
     * @return string|false 切片结果
     */
    public static function cut(AisleInterface $aisle): string|false;

    /**
     * 抛弃脏数据，调整通道指针
     *
     * @param \Cclilshy\PRipple\Communication\Standard\AisleInterface $aisle
     * @return string|false
     */
    public static function corrective(AisleInterface $aisle): string|false;

}