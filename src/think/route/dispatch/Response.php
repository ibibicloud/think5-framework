<?php

namespace think\route\dispatch;

use think\route\Dispatch;

class Response extends Dispatch
{
    public function exec()
    {
        return $this->dispatch;
    }

}
