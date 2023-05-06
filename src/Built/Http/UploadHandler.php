<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Http;

class UploadHandler
{
    const STATUS_ILLEGAL            = -1;
    const STATUS_WAITING_FOR_HEADER = 0;
    const STATUS_TRANSMITTING       = 1;

    public array      $files  = array();
    protected string  $currentTransferFilePath;
    protected int     $status;
    protected mixed   $currentTransferFile;
    protected string  $buffer = '';
    protected string  $boundary;
    protected Request $request;

    public function __construct(string $boundary, Request $request)
    {
        $this->boundary = $boundary;
        $this->status   = UploadHandler::STATUS_WAITING_FOR_HEADER;
        $this->request  = $request;
    }

    public function __sleep()
    {
        return ['files'];
    }

    public function push(string $context): void
    {
        // buffer store data
        $this->buffer .= $context;
        while ($this->status !== UploadHandler::STATUS_ILLEGAL) {
            if ($this->status === UploadHandler::STATUS_WAITING_FOR_HEADER) {
                if (!$this->parseFileInfo()) {
                    break;
                }
            }

            if ($this->status === UploadHandler::STATUS_TRANSMITTING) {
                if (!$this->processTransmitting()) {
                    break;
                }
            }
        }
    }

    private function processTransmitting(): bool
    {
        $boundaryPosition = strpos($this->buffer, "\r\n--" . $this->boundary);
        if ($boundaryPosition !== false) {
            $remainingData        = substr($this->buffer, $boundaryPosition + 2);
            $nextBoundaryPosition = strpos($remainingData, "\r\n--" . $this->boundary);
            if ($nextBoundaryPosition === false && !str_starts_with($remainingData, '--')) {
                // 不完整的boundary，暂时保存在缓冲区中，等待下次push时处理
                return false;
            }

            $content      = substr($this->buffer, 0, $boundaryPosition);
            $this->buffer = $remainingData;
            fwrite($this->currentTransferFile, $content);
            fclose($this->currentTransferFile);

            $this->status = UploadHandler::STATUS_WAITING_FOR_HEADER;
            return true;
        } else {
            $content      = $this->buffer;
            $this->buffer = '';

            fwrite($this->currentTransferFile, $content);
            return false;
        }
    }

    private function parseFileInfo(): bool
    {
        $headerEndPosition = strpos($this->buffer, "\r\n\r\n");
        if ($headerEndPosition === false) {
            return false;
        }

        $header       = substr($this->buffer, 0, $headerEndPosition);
        $this->buffer = substr($this->buffer, $headerEndPosition + 4);

        $lines = explode("\r\n", $header);

        $boundaryLine = array_shift($lines);
        if ($boundaryLine !== '--' . $this->boundary) {
            $this->request->signStatusCode(Request::INVALID);
            $this->status = UploadHandler::STATUS_ILLEGAL;
            return false;
        }

        $fileInfo = array();
        foreach ($lines as $line) {
            if (preg_match('/^Content-Disposition: form-data; name="([^"]+)"; filename="([^"]+)"$/i', $line, $matches)) {
                $fileInfo['name']     = $matches[1];
                $fileInfo['fileName'] = $matches[2];
            } elseif (preg_match('/^Content-Type: (.+)$/i', $line, $matches)) {
                $fileInfo['contentType'] = $matches[1];
            }
        }

        if (empty($fileInfo['name']) || empty($fileInfo['fileName'])) {
            $this->request->signStatusCode(Request::INVALID);
            $this->status = UploadHandler::STATUS_ILLEGAL;
            return false;
        }

        $this->createNewFile($fileInfo);
        $this->status = UploadHandler::STATUS_TRANSMITTING;
        return true;
    }


    private function createNewFile(array $fileInfo): void
    {
        $this->currentTransferFilePath = PRIPPLE_CACHE_PATH . FS . getRandHash();
        $this->currentTransferFile     = fopen($this->currentTransferFilePath, 'wb+');

        $fileInfo['path'] = $this->currentTransferFilePath;
        $this->files[]    = $fileInfo;
    }
}