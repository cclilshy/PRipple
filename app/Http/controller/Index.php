<?php
/*
 * @Author: cclilshy jingnigg@gmail.com
 * @Date: 2023-03-30 16:29:26
 * @LastEditors: cclilshy jingnigg@gmail.com
 * Copyright (c) 2023 by user email: jingnigg@gmail.com, All Rights Reserved.
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
        $this->assign('name', 'PRipple');
        return $this;
    }

    public function upload(): string
    {
        if ($this->request->isUpload) {
            return json_encode($this->request->uploadBaseInfo ?? []);
        } else {
            return $this;
        }
    }
}
