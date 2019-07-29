<?php

namespace Makeable\Throttle\Concerns;

use Illuminate\Support\Collection;
use Makeable\Throttle\Dispatcher;

trait BuildsCallbacks
{
    protected $callbacks = [
        'catch' => [],
        'run' => [],
        'failed' => []
    ];

    /**
     * @param $callback
     * @param bool $append
     * @return $this
     */
    public function run($callback, $append = true)
    {
        return $this->addCallback("run", $callback, $append);
    }

    /**
     * @param string|int $error
     * @param $callback
     * @param bool $append
     * @return $this
     */
    public function catch($error, $callback = null, $append = true)
    {
        if ($callback === null) {
            $callback = $error;
            $error = '*';
        }

        return $this->addCallback("catch.{$error}", $callback, $append);
    }

    /**
     * @param $callback
     * @param bool $append
     * @return $this
     */
    public function failed($callback, $append = true)
    {
        return $this->addCallback("failed", $callback, $append);
    }

    /**
     * @param string|int $error
     * @param null $delay
     * @return $this
     */
    public function retryOnError($error, $delay = null)
    {
        return $this->catch($error, function () use ($delay) {
            $this->retry($delay);
        });
    }

    /**
     * @param $group
     * @param callable $callback
     * @param bool $append
     * @return $this
     */
    protected function addCallback($group, callable $callback, bool $append)
    {
        $callbacks = data_get($this->callbacks, $group, []);
        $callbacks[] = $callback;

//        data_set($this->callbacks, $group, $append ? $callbacks : [$callback]);

        return $this;
    }

    /**
     * @param $group
     * @return Collection
     */
    protected function getCallbacks($group)
    {
        return collect(data_get($this->callbacks, $group, []));
    }

    /**
     * @param mixed ...$args
     * @return \Closure
     */
    protected function invoke(...$args)
    {
        return function ($callback) use ($args) {
            call_user_func($callback, ...$args);
        };
    }
}