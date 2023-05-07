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
        $this->assign('name','PRipple');
        return $this;
    }

    public function upload(): string
    {
        if ($this->request->isUpload) {
            $data = [];
            while ($event = $this->wait()) {
                switch ($event->getName()) {
                    case 'CompleteUploadFile':
                        break;
                    case 'NewUploadFile':
                        $data = $event->getData();
                        break;
                    case 'RequestComplete':
                        return json_encode($data);
                }
            }
        } else {
            return $this;
        }
    }
}