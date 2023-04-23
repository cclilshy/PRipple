<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\Built\Process\Standard;


/**
 *
 */
interface EventInterface
{
    /**
     * @param callable    $observer
     * @param mixed|null  $space
     * @param string|null $name
     * @return \Cclilshy\PRipple\Service\Process\Event|false
     */
    public static function create(callable $observer, mixed $space = null, string $name = null): EventInterface|false;

    /**
     * @param string    $name
     * @param bool|null $destroy
     * @return \Cclilshy\PRipple\Service\Process\Event|false
     */
    public static function link(string $name, ?bool $destroy = false): EventInterface|false;

    /**
     * @return mixed
     */
    public function call(): mixed;
}