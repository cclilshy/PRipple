<?php

/** @noinspection ALL */
/** @noinspection ALL */

/*
 * @Author: cclilshy cclilshy@163.com
 * @Date: 2022-12-06 16:14:15
 * @LastEditors: cclilshy jingnigg@gmail.com
 * @Description: PRipple
 * Copyright (c) 2022 by cclilshy email: cclilshy@163.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Http\Text;

use Cclilshy\Console;
use Cclilshy\PRipple\Built\Http\Http;
use function substr;
use function is_string;
use function htmlspecialchars;

// Application level, used for template parsing and template rendering
class Plaster
{
    private static $regexs = array(
        'foreach' => '/@foreach[\t\s]*\([\t\s]*(?:(?:(\$\w+(?:\[(?:\$*\w+|(?:"(?:[^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'(?:[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'))\])?)[\t\s]+as[\t\s]+(\$\w+))(?:[\t\s]*=>[\t\s]*(\g<1>))?[\t\s]*\))\R((?:\g<0>|(?!(@foreach|@endforeach))[\s\S]*?)+\R)?[\t\s]*@endforeach/',
        'for'     => '/@for[\s\t]*\(([\s\S]*?)?\;([\s\S]*?)\;([\s\S]*?)\)[\s\t]*((?:\g<0>|(?!(@for|@endfor))[\s\S]*?)+\R)[\t\s]*@endfor/',
        'while'   => '/@while[\s\t]*\(([\s\S]*?)\)\R+((?:\g<0>|(?!(@while|@endwhile))[\s\S]*?)+\R)?[\t\s]*@endwhile/',
        'if'      => '/@?(?:if|elseif)[\s\t]*\(([\s\S]*?)\)\R([\s\S]*?)\R[\s\t]*(@else([\s\S]*?)\R)?[\s\t]*@endif/',
        'embed'   => '/@embed[\s]*\((.*){1}\)[\s]*@endembed/'
    );
    /**
     * 模板变量暂存区
     *
     * @var array|mixed
     */
    private $arguments = array();

    /**
     * 允许在实例时直接定义变量
     *
     * @param $defined
     */
    public function __construct($defined = null)
    {
        if ($defined) {
            $this->arguments = $defined;
        }
    }

    /**
     * 使用静态方法渲染
     *
     * @param string $content
     * @param array  $defined
     * @return string
     */
    public static function draw(string $content, array $defined): string
    {
        return call_user_func([new self($defined), 'apply'], $content);
    }

    /**
     * @param string $key
     * @param        $value
     * @return void
     */
    public function assign(string $key, $value): void
    {
        $this->arguments[$key] = $value;
    }

    /**
     * @param array $executeForArguments
     * @param array $tempArgv
     * @return string
     */
    private function analyFor(array $executeForArguments, array $tempArgv = array()): string
    {
        foreach (array_merge($this->arguments, $tempArgv) as $executeIfForeachKey => $executeIfForeachValue) {
            $$executeIfForeachKey = $executeIfForeachValue;
        }
        $executeForStart     = $executeForArguments[1];
        $executeForCondition = $executeForArguments[2];
        $executeForEnd       = $executeForArguments[3];
        $executeForFarmment  = $executeForArguments[4];
        $templateFramment    = '';
        try {
            eval("{$executeForStart};");
            while (eval("return {$executeForCondition};")) {
                $templateFramment .= self::apply($executeForFarmment, get_defined_vars());
                eval("{$executeForEnd};");
            }
        } catch (\Throwable $e) {
            Console::pred($e->getMessage());
            return '';
            // \core\Request::htmlErrorPage($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
        }

        return str_replace($executeForArguments[0], $templateFramment, $executeForArguments[0]);
    }

    /**
     * 渲染的递归入口
     *
     * @param string $applyTemplateText
     * @param array  $tempArguments
     * @return string
     */
    public function apply(string $applyTemplateText, array $tempArguments = array()): string
    {
        //定义变量
        extract(array_merge($this->arguments, $tempArguments));
        $applyLableCircuitStack = array();
        $applyTemplateTextPoint = 0;
        while ($applyTemplateTextPoint < strlen($applyTemplateText)) {
            $applyLableCircuitStackCount = count($applyLableCircuitStack);
            $applyTemplateTextPointChar  = substr($applyTemplateText, $applyTemplateTextPoint++, 1);
            if ($applyTemplateTextPointChar === '@') {
                foreach (self::$regexs as $applyForeachAssignmentKey => $applyForeachAssignmentValue) {
                    if (strpos($applyTemplateText, $applyForeachAssignmentKey, $applyTemplateTextPoint) === $applyTemplateTextPoint) {
                        if ($applyLableCircuitStackCount === 0)
                            $startIndex = $applyTemplateTextPoint - 1;
                        $applyLableName           = $applyForeachAssignmentKey;
                        $applyLableCircuitStack[] = $applyForeachAssignmentKey;
                        break;
                    } elseif (end($applyLableCircuitStack) !== false && strpos($applyTemplateText, 'end' . end($applyLableCircuitStack), $applyTemplateTextPoint) === $applyTemplateTextPoint) {
                        $applyLableName = array_pop($applyLableCircuitStack);
                        break;
                    }
                }
            }

            if ($applyLableCircuitStackCount === 1 && count($applyLableCircuitStack) === 0) {
                $applyTemplateFragment       = substr($applyTemplateText, $startIndex, $applyTemplateTextPoint - $startIndex + strlen('end' . $applyLableName));
                $applyTemplateFragmentResult = self::analy($applyTemplateFragment, $applyLableName, get_defined_vars());
                if ($applyTemplateFragmentResult !== false) {
                    $applyTemplateText      = str_replace($applyTemplateFragment, $applyTemplateFragmentResult, $applyTemplateText);
                    $applyTemplateTextPoint += strlen($applyTemplateFragmentResult) - strlen($applyTemplateFragment);
                }
            }
        }

        //处理花括号
        $applyBraceMatchCount = preg_match_all('/@?\{\{((?:[^\}\\\\]*(?:\\\\.[^}\\\\]*)*))\}\}/', $applyTemplateText, $applyBraceMatchResult);
        for ($applyBracecExecIndex = 0; $applyBracecExecIndex < $applyBraceMatchCount; $applyBracecExecIndex++) {
            if (strpos($applyBraceMatchResult[0][$applyBracecExecIndex], '@') === 0) {
                $applyTemplateText = str_replace($applyBraceMatchResult[0][$applyBracecExecIndex], substr($applyBraceMatchResult[0][$applyBracecExecIndex], 1), $applyTemplateText);
            } else {
                try {
                    $applyBracecExecResult = eval("return {$applyBraceMatchResult[1][$applyBracecExecIndex]};");
                } catch (\Throwable $e) {
                    Console::pred($e->getMessage());
                    return '';
                    // \core\Request::htmlErrorPage($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
                }

                if ($applyBracecExecResult !== false)
                    if (is_string($applyBracecExecResult)) {
                        $applyBracecExecResult = htmlspecialchars($applyBracecExecResult);
                    }
                $applyTemplateText = str_replace($applyBraceMatchResult[0][$applyBracecExecIndex], $applyBracecExecResult ?? '', $applyTemplateText);
            }
        }

        //处理所有PHP语句
        $phpFrammentMatchCount = preg_match_all('/@php([\s\S]+?)@endphp/', $applyTemplateText, $phpFrammentMatchResult);
        for ($phpFrammentMatchIndex = 0; $phpFrammentMatchIndex < $phpFrammentMatchCount; $phpFrammentMatchIndex++) {
            ob_start();
            try {
                eval($phpFrammentMatchResult[1][$phpFrammentMatchIndex]);
            } catch (\Throwable $e) {
                Console::pred($e->getMessage());
                return '';
                // \core\Request::htmlErrorPage($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
            }
            $output            = ob_get_clean();
            $applyTemplateText = str_replace($phpFrammentMatchResult[0][$phpFrammentMatchIndex], $output, $applyTemplateText);
        }
        return $applyTemplateText;
    }

    /**
     * 区段类型调度
     *
     * @param string $content
     * @param string $tag
     * @param array  $arguments
     * @return string|false
     */
    private function analy(string $content, string $tag, array $arguments): string|false
    {
        $matchc = preg_match(self::$regexs[$tag], $content, $matchs);
        if ($matchc > 0) {
            return call_user_func([$this, 'analy' . ucfirst($tag)], $matchs, $arguments);
        }
        return false;
    }

    /**
     * @param array $arguments
     * @param array $tempArgv
     * @return string
     */
    private function analyWhile(array $arguments, array $tempArgv = array()): string
    {
        foreach (array_merge($this->arguments, $tempArgv) as $executeIfForeachKey => $executeIfForeachValue) {
            $$executeIfForeachKey = $executeIfForeachValue;
        }
        $executeWhileFarmment = '';
        try {
            while (eval("return {$arguments[1]};")) {
                $executeWhileFarmment .= self::apply($arguments[2], get_defined_vars());
            }
        } catch (\Throwable $e) {
            Console::pred($e->getMessage());
            return '';
            // \core\Request::htmlErrorPage($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
        }
        return $executeWhileFarmment;
    }

    /**
     * @param array $arguments
     * @param array $tempArgv
     * @param bool  $end
     * @return string
     */
    private function analyIf(array $arguments, array $tempArgv = array(), bool $end = false): string
    {
        foreach (array_merge($this->arguments, $tempArgv) as $executeIfForeachKey => $executeIfForeachValue) {
            $$executeIfForeachKey = $executeIfForeachValue;
        }
        $executeIfCondition = $arguments[1];
        $executeIfAccord    = $arguments[2];
        $executeIfElse      = $arguments[3] ?? null;

        try {
            if (eval("return {$executeIfCondition};")) {
                return $this->apply($executeIfAccord);
            } elseif ($executeIfElse !== null) {
                if (substr($executeIfElse, 0, 2) === 'if') {
                    return $this->apply('@' . $executeIfElse . '@endif');
                } else {
                    return $this->apply($executeIfAccord);
                }
            } else {
                return '';
            }
        } catch (\Throwable $e) {
            Console::pred($e->getMessage());
            return '';
            // \core\Request::htmlErrorPage($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
        }
    }

    /**
     * 处理foreach区段
     *
     * @param array $arguments
     * @param array $tempArgv
     * @return string
     */
    private function analyForeach(array $arguments, array $tempArgv = array()): string
    {
        extract(array_merge($this->arguments, $tempArgv));
        $originContent             = $arguments[0];
        $templateFrammentArrayName = $arguments[1];
        $templateFrammentKeyName   = $arguments[2];
        $templateFrammentItemName  = $arguments[3];
        $templateFrammentContent   = $arguments[4];
        if ($templateFrammentItemName === '')
            $templateFrammentItemName = $templateFrammentKeyName;
        foreach ($this->arguments as $applyForeachAssignmentKey => $applyForeachAssignmentValue)
            $$applyForeachAssignmentKey = $applyForeachAssignmentValue;
        $templateFrammentHtml = '';
        try {
            $templateFrammentArray = eval('return ' . $templateFrammentArrayName . ';');
        } catch (\Throwable $e) {
            Console::pred($e->getMessage());
            return '';
            // \core\Request::htmlErrorPage($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
        }
        $templateFrammentIndex = 0;
        foreach ($templateFrammentArray as $templateFrammentKey => $templateFrammentValue) {
            $templateFrammentHtml .= self::apply($templateFrammentContent, [
                substr($templateFrammentKeyName, 1)  => $templateFrammentKey,
                substr($templateFrammentItemName, 1) => $templateFrammentValue,
            ]);
            $templateFrammentIndex++;
        }
        return $templateFrammentHtml;
    }

    /**
     * 模板包涵
     *
     * @param array $arguments
     * @param array $tempArgv
     * @return string
     */
    private function analyEmbed(array $arguments, array $tempArgv = array()): string
    {
        $executeEmbedTemplateFile = trim($arguments[1], '"\'');
        //        $executeEmbedTemplateContent
        return file_get_contents(Http::TEMPLATE_PATH . FS . $executeEmbedTemplateFile . '.' . \Cclilshy\PRipple\Config::get('HttpService.template_extension'));
    }
}
