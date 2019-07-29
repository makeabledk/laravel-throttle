<?php

namespace Makeable\Throttle;

class SyncThrottle
{
    public function __call($name, $arguments)
    {
        //
    }

    public function __get($param)
    {
        //
    }

    public function __set($param, $value)
    {
        //
    }

    public function then($callback)
    {
        $callback();
    }
}