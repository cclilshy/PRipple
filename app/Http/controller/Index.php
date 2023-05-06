<?php
/*
 * @ Work name: PRipple
 * @ Author: cclilshy jingnigg@gmail.com
 * @ Copyright (c) 2023. by user email: jingnigg@gmail.com, All Rights Reserved.
 */

namespace app\Http\controller;

use Cclilshy\PRipple\Built\Http\Request;
use Cclilshy\PRipple\Built\Http\Controller;

class Index extends Controller
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    public function index(): string
    {
        return 'hello world';
    }

    public function upload(): string
    {
        if ($this->request->isUpload) {
            $fileSize = filesize($this->request->uploadHandler->currentTransferFilePath);
            $data     = [
                'filePath' => $this->request->uploadHandler->currentTransferFilePath,
                'fileSize' => $fileSize,
                'md5'      => md5_file($this->request->uploadHandler->currentTransferFilePath)
            ];
            copy($this->request->uploadHandler->currentTransferFilePath,'/Users/cclilshy/Dev/cloudtay/res.txt');
            return json_encode($data);
        } else {
            return $this;
        }
    }
}