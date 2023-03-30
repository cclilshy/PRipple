<?php
declare(strict_types=1);
/*
 * @Author: cclilshy cclilshy@163.com
 * @Date: 2023-03-04 03:14:33
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: PRipple
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple;

class Statistics
{
    public array $loadFiles = [];       // 加载的文件
    public array $posts     = [];       // 所有POST内容
    public array $gets      = [];       // 所有GET内容
    public array $sqlps     = [];       // SQL查询记录
    public float $memory;               // 内存用量
    public float $maxMemory;            // 内存峰值
    public float $startTime = 0;        // 运行时时间，在对象创建时会自动创建
    public float $endTime   = 0;        // 结尾时间
    public mixed $space;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->record('endTime', null);
    }

    /**
     * 记录指定数据
     *
     * @param string|null $type
     * @param mixed       $data
     * @return $this
     */
    public function record(string|null $type, mixed $data): Statistics
    {
        switch ($type) {
            case 'sql':
                $this->sqlps[] = $data;
                break;
            case 'file':
                break;
            case 'space':
                $this->space = $data;
                break;

        }

        // 每次记录时会重新载入这些值
        $this->loadFiles = get_included_files();
        $this->endTime   = microtime(true);
        $this->memory    = memory_get_usage();
        $this->maxMemory = memory_get_peak_usage();
        return $this;
    }
}
