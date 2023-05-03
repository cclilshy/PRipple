<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

function strToBytes(string $str): int
{
    $last = strtolower($str[strlen($str) - 1]);
    $num  = (int)substr($str, 0, -1);

    if ($last === 'g') {
        $num *= 1024;
    }
    if ($last === 'm') {
        $num *= 1024;
    }
    if ($last === 'k') {
        $num *= 1024;
    }
    return $num;
}