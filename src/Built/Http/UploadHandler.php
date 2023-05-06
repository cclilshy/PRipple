<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace Cclilshy\PRipple\Built\Http;
class UploadHandler
{
    // -1: This packet is illegal,
    // 0: Waiting for analysis,
    // 1: Transmitting
    public string    $currentTransferFilePath;
    protected int    $status;
    protected mixed  $currentTransferFile;
    protected array  $files     = array();
    protected array  $lineStack = array();
    protected string $buffer    = '';
    protected string $boundary;

    public function __construct(string $boundary)
    {
        $this->boundary = $boundary;
        $this->status   = 0;
    }

    public function __sleep()
    {
        return ['files'];
    }

    public function push(string $context): bool
    {
        // buffer store data
        $this->buffer .= $context;

        // Judging the transmission status
        switch ($this->status) {
            case 0:
                //TODO: Attempt to parse file information
                if (str_contains($this->buffer, "\r\n\r\n")) {
                    $this->bufferPushOntoStack();
                    if ($this->parseFileInfo()) {
                        return $this->lineStackConsume();
                    }
                }
                return true;
            case 1:
                //TODO: Brainless transfer process
                $this->bufferPushOntoStack();
                $result = $this->lineStackConsume();
                if ($this->status === 0 && $this->parseFileInfo()) {
                    //TODO: Single file transfer completed, recursively process subsequent files
                    $this->lineStackConsume();
                }
                return $result;
            case -1:
            default:
                return false;
        }
    }

    private function bufferPushOntoStack(): void
    {
        $array = explode("\r\n", $this->buffer);
        foreach ($array as $item) {
            $this->lineStack[] = $item;
        }
        $this->buffer = '';
    }

    private function parseFileInfo(): bool
    {
        $line = array_shift($this->lineStack);
        if (empty($line) || !str_starts_with($line, '--' . $this->boundary)) {
            return false;
        }
        // TODO: Parse the basic information of the uploaded file
        $theFileInfo        = array();
        $this->status       = 1;
        $dispositionAndName = explode(';', array_shift($this->lineStack));
        $disposition        = explode(':', $dispositionAndName[0])[1];
        $name               = explode('=', $dispositionAndName[1])[1];
        $fileName           = explode('=', $dispositionAndName[2])[1];
        $contentType        = explode(':', array_shift($this->lineStack))[1] ?? '';
        array_shift($this->lineStack);
        $this->createNewFile();
        $theFileInfo['disposition'] = trim($disposition);
        $theFileInfo['name']        = trim($name, '" ');
        $theFileInfo['fileName']    = trim($fileName, '" ');
        $theFileInfo['contentType'] = trim($contentType);
        $theFileInfo['path']        = $this->currentTransferFilePath;
        $this->files[]              = $theFileInfo;
        return true;
    }

    /**
     * @return void
     */
    private function createNewFile(): void
    {
        $this->currentTransferFilePath = PRIPPLE_CACHE_PATH . FS . md5(microtime(true) . rand(1, 9));
        $this->currentTransferFile     = fopen($this->currentTransferFilePath, 'wb+');
    }

    private function lineStackConsume(): bool
    {
        $i         = 0;
        $lastIndex = count($this->lineStack) - 1;
        $first     = true;
        while ($this->status === 1 && ($line = array_shift($this->lineStack)) !== null) {
            $i++;
            if (!empty($line) && str_contains('--' . $this->boundary . '--', $line)) {
                //TODO: PROBABLY END OF FILE
                $this->status = 0;
                fclose($this->currentTransferFile);
            } elseif (empty($line)) {
                //TODO: BLANK LINE
                fwrite($this->currentTransferFile, "\r\n");
            } else {
                //TODO: non-null persistent data
                fwrite($this->currentTransferFile, $line);
                if ($first) {
                    $first = false;
                } elseif ($lastIndex !== $i) {
                    fwrite($this->currentTransferFile, "\r\n");
                }
            }
        }
        return true;
    }
}