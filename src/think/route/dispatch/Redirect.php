<?php

namespace think\route\dispatch;

use think\Response;
use think\route\Dispatch;

class Redirect extends Dispatch
{
    public function exec()
    {
        return Response::create($this->dispatch, 'redirect')->code($this->code);
    }
}
