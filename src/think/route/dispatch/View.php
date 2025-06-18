<?php

namespace think\route\dispatch;

use think\Response;
use think\route\Dispatch;

class View extends Dispatch
{
    public function exec()
    {
        // 渲染模板输出
        $vars = array_merge($this->request->param(), $this->param);

        return Response::create($this->dispatch, 'view')->assign($vars);
    }
}
