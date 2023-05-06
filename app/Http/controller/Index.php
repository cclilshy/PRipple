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
        return $this;
    }

    public function upload(): string
    {
        if ($this->request->isUpload) {
            return json_encode($this->request->uploadHandler->files);
        } else {
            return $this;
        }
    }
}