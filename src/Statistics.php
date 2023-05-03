<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

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
    public array $loadFiles = [];       // loaded files
    public array $posts     = [];       // All POST content
    public array $gets      = [];       // All GET content
    public array $sqlps     = [];       // SQL query records
    public float $memory;               // memory usage
    public float $maxMemory;            // memory peak
    public float $startTime = 0;        // Runtime time, which is automatically created when the object is created
    public float $endTime   = 0;        // end time
    public mixed $space;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->record('endTime', null);
    }

    /**
     * Record specified data
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

        // These values are reloaded each time you lo
        $this->loadFiles = get_included_files();
        $this->endTime   = microtime(true);
        $this->memory    = memory_get_usage();
        $this->maxMemory = memory_get_peak_usage();
        return $this;
    }
}
