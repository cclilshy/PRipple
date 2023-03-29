<?php

namespace Cclilshy\PRipple;
use Cclilshy\PRipple\Dispatch\Dispatcher;

class Console
{
    /**
     * 输出信息
     *
     * @param mixed $message
     * @return void
     */
    public static function log(mixed $message): void
    {
        echo self::format($message, "\n");
    }

    /**
     * 格式化输出信息
     *
     * @param mixed  $message
     * @param string $prefix
     * @return string
     */
    private static function format(mixed $message, string $prefix): string
    {
        return $prefix . print_r($message, true);
    }

    /**
     * 输出警告信息
     *
     * @param mixed $message
     * @return void
     */
    public static function warn(mixed $message): void
    {
        echo self::format($message, "\033[33m[Warning]\033[0m\n");
    }

    /**
     * 输出错误信息
     *
     * @param mixed $message
     * @return void
     */
    public static function error(mixed $message): void
    {
        echo self::format($message, "\033[31m[Error]\033[0m\n");
    }

    /**
     * 服务状态输出模式方法
     *
     * @param mixed  $message
     * @param string $state
     * @return void
     */
    public static function serviceStatus(mixed $message, string $state): void
    {
        $stateColor = self::getStateColor($state);
        echo self::format($message, $stateColor . " [$state]\033[0m\n");
    }

    /**
     * 获取服务状态的颜色代码
     *
     * @param string $state
     * @return string
     */
    private static function getStateColor(string $state): string
    {
        return match ($state) {
            'STOPPING', 'RESTARTING', 'STARTING' => "\033[33m",
            'RUNNING'                            => "\033[32m",
            'ERROR', 'STOPPED'                   => "\033[31m",
            default                              => "\033[0m",
        };
    }

    public static function debug(): void
    {
        $arguments = func_get_args();
        $text      = '';
        while ($argument = array_shift($arguments)) {
            if (is_callable($argument)) {
                $_ = 'function';
            } elseif (is_resource($argument)) {
                $_ = 'resource';
            } elseif (is_object($argument) || is_array($argument)) {
                $_ = json_encode($argument);
            } elseif (is_string($argument)) {
                $_ = $argument;
            } else {
                $_ = gettype($argument);
            }
            $text .= $_ . '|';
        }
        Dispatcher::noticeControl($text);
        self::coloredDebug($text, 'yellow');
    }

    /**
     * 带颜色的调试输出方法
     *
     * @param mixed  $message
     * @param string $color
     * @return void
     */
    public static function coloredDebug(mixed $message, string $color): void
    {
        $colorCode = self::getColorCode($color);
        echo self::format($message . "\n", "\033[36m[Debug]\033[0m" . $colorCode);
    }

    /**
     * 获取颜色代码
     *
     * @param string $color
     * @return string
     */
    private static function getColorCode(string $color): string
    {
        return match ($color) {
            'red'    => "\033[31m",
            'green'  => "\033[32m",
            'yellow' => "\033[33m",
            'blue'   => "\033[34m",
            'purple' => "\033[35m",
            'cyan'   => "\033[36m",
            'white'  => "\033[37m",
            default  => "\033[0m",
        };
    }

    public static function displayTableFromArray(array $tableArray, string $tableName): void
    {
        if (empty($tableArray['header']) || empty($tableArray['body'])) {
            echo "No data to display.";
            return;
        }

        // Determine maximum length of each column
        $columnMaxLen = array_map(function ($column) {
            return $column[1];
        }, $tableArray['header']);

        // Output table name
        $tableNameColor = "\033[1;32m"; // Bold cyan
        printf("%s%s:\n%s", $tableNameColor, $tableName, "\033[0m");

        // Output table top border
        $borderStr = "+-" . str_repeat('-', array_sum($columnMaxLen) + count($columnMaxLen) * 2) . "-+\n";
        printf("%s", $borderStr);

        // Output table header
        $headerStr = '';
        foreach ($tableArray['header'] as $i => $column) {
            $headerStr .= "| \033[1;36m%{$columnMaxLen[$i]}s\033[0m ";
        }
        $headerStr .= "|\n";
        printf($headerStr, ...array_column($tableArray['header'], 0));

        // Output table mid border
        printf("%s", $borderStr);

        // Output table rows
        foreach ($tableArray['body'] as $row) {
            $rowStr = '';
            foreach ($row as $i => $column) {
                $rowStr .= "| %{$columnMaxLen[$i]}s ";
            }
            $rowStr .= "|\n";
            printf($rowStr, ...$row);
        }

        // Output table bottom border
        printf("%s", $borderStr);
    }


}
