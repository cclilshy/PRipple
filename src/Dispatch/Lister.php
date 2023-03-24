<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-21 22:19:36
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Dispatch;

use Cclilshy\PRipple\Dispatch\standard\ServiceAbstract;
use Cclilshy\PRipple\Communication\Standard\AisleInterface;
use Cclilshy\PRipple\Dispatch\standard\EventTemplateAbstract;

/**
 * 容易混淆调用栈,主进程会在创建子进程后立即返回，
 */
class Lister
{
    const FORMAT_BUILD   = 1;  // 消息包
    const FORMAT_EVENT   = 2;  // 事件
    const FORMAT_MESSAGE = 3;  // 消息

    const E_ORIGINAL_CONTEXT = 4;

    /**
     * @var int 监听者的子进程ID（监听工作的进程ID
     */
    private int $listerProcessId;

    /**
     * 事件处理器的名称
     *
     * @var string
     */

    private string $eventHandlerName;

    /**
     * 事件处理器的实体
     *
     * @var \Cclilshy\PRipple\Dispatch\standard\ServiceAbstract
     */
    private ServiceAbstract $eventHandler;

    /**
     * 事件处理器套接字Socket实体对象
     *
     * @var mixed
     */
    private mixed $dispatcherSocket;

    /**
     * 实现该应用通用的通信规范对象
     *
     * @var \Cclilshy\PRipple\Communication\Standard\AisleInterface
     */
    private AisleInterface $dispatcherAisle;

    /**
     * !!! 以上属性作用与子进程与主进程不在相同调用栈
     * 这个属性是主进程中与子进程连接的标准通信规范对象
     *
     * @var \Cclilshy\PRipple\Communication\Standard\AisleInterface
     */
    private AisleInterface $listerNoticer;

    /**
     * 创建监听者（父）
     *
     * @param \Cclilshy\PRipple\Dispatch\standard\ServiceAbstract $eventHandler
     */
    private function __construct(ServiceAbstract $eventHandler)
    {
        $this->eventHandler = $eventHandler;
    }

    /**
     * 事件处理器实体（父）
     *
     * @return false|static
     */
    public static function launch(ServiceAbstract $eventHandler): self|false
    {
        $lister = new self($eventHandler);
        return $lister->listen() > 0 ? $lister : false;
    }

    /**
     * 启用监听服务（父）
     * 子进程已经不可能回来了
     *
     * @return int|false
     */
    private function listen(): int|false
    {
        $this->eventHandlerName = get_class($this->eventHandler);
        switch ($listerProcessId = pcntl_fork()) {
            case 0:
                // 订阅前注册，返回true为注册，返回false为不注册
                if (call_user_func([$this->eventHandler, 'register'])) {
                    // 激活通信
                    $this->listerProcessId  = posix_getpid();
                    $this->dispatcherSocket = Dispatcher::LOCAL_STREAM_TYPE::connect(Dispatcher::UNIX_SERVER);
                    $this->dispatcherAisle  = Dispatcher::SERVER_AISLE_TYPE::create($this->dispatcherSocket);
                    Dispatcher::AGREE::send($this->dispatcherAisle, $this->eventHandlerName);
                    call_user_func([$this->eventHandler, 'endowedCommunication'], $this->dispatcherAisle);
                } else {
                    return false;
                }

                // 开始监听
                $this->work();
                //TODO::考虑资源释放
                exit;
            case -1:
                return false;
            default:
                return $this->listerProcessId = $listerProcessId;
        }
    }

    /**
     * 开始工作（子）
     *
     * @return void
     */
    public function work(): void
    {
        while (true) {
            $readList   = [$this->dispatcherSocket];
            $writeList  = [];
            $exceptList = [];
            socket_select($readList, $writeList, $exceptList, null);
            $int = 0;
            if ($context = Dispatcher::AGREE::cutWithInt($this->dispatcherAisle, $int)) {
                switch ($int) {
                    case Lister::FORMAT_BUILD:
                        if ($build = Build::unSerialize($context)) {
                            call_user_func([$this->eventHandler, 'packHandle'], $build);
                        }

                        break;
                    case Lister::FORMAT_EVENT:
                        if ($eventBuild = unserialize($context)) {
                            call_user_func([$this->eventHandler, 'eventHandle'], $eventBuild);
                        }
                        break;
                    case Lister::FORMAT_MESSAGE:
                        call_user_func([$this->eventHandler, 'messageHandle'], $context);
                        break;
                    default:
                }
            } else {
                break;
            }
        }
    }

    /**
     * 发送通知（父）
     * 子进程执行work后，该方法生效
     *
     * @param int                                                                                               $messageType @ 消息类型
     * @param \Cclilshy\PRipple\Dispatch\standard\EventTemplateAbstract|\Cclilshy\PRipple\Dispatch\Build|string $data        @ 消息数据
     * @return bool
     */
    public function notice(int $messageType, EventTemplateAbstract|Build|string $data): bool
    {
        // 根据类型选取特定方式发送，协议新`sendWithInt`方法支持携带一个4字节的整形数据
        switch ($messageType) {
            case Lister::FORMAT_EVENT:
            case Lister::FORMAT_BUILD:
                $context = $data->serialize();
                break;

            case Lister::FORMAT_MESSAGE:
                // TODO:: 文本消息直接明文发送提高效率
                $context = $data;
                break;
            default:
                return false;
        }
        return Dispatcher::AGREE::sendWithInt($this->listerNoticer, $context, $messageType);
    }

    /**
     * 调度器注册完整后，会在特定的时间分配s通信规范对象（父）
     *
     * @param \Cclilshy\PRipple\Communication\Standard\AisleInterface $aisle
     * @return $this
     */
    public function setListerAisle(AisleInterface $aisle): self
    {
        $this->listerNoticer = $aisle;
        return $this;
    }

    /**
     * 获取子进程ID（父）
     *
     * @return int
     */
    public function getProcessId(): int
    {
        return $this->listerProcessId;
    }

    /**
     * 获取事件处理器名称（通）
     *
     * @return string
     */
    public function getEventHandlerName(): string
    {
        return $this->eventHandlerName;
    }

    /**
     * 杀死子进程(通)
     *
     * @return bool
     */
    public function kill(): bool
    {
        //TODO::.........太粗鲁
        return posix_kill($this->listerProcessId, SIGKILL);
    }
}
