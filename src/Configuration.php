<?php

namespace Makeable\Throttle;

use Illuminate\Bus\Queueable;
use Illuminate\Redis\Limiters\DurationLimiterBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;

class Configuration
{
    /**
     * The default amount of tries.
     *
     * @var int | null
     */
    public static $defaultTries;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var
     */
    protected $throttle;

    /**
     * Throttle constructor.
     *
     * @param $name
     * @param bool $supportsRedis
     */
    public function __construct($name, $supportsRedis = true)
    {
        $this->config = [
            'payload' => [
                'tries' => static::$defaultTries ?? config('horizon.environments.'.app()->environment().'.supervisor-1.tries') ?? 3,
            ]
        ];

        $this->throttle = $supportsRedis
            ? Redis::throttle($name)
            : app(SyncThrottle::class);
    }

    // _________________________________________________________________________________________________________________

    /**
     * @param $seconds
     * @return $this
     */
    public function everySeconds($seconds)
    {
        $this->throttle->every($seconds);

        return $this;
    }

    /**
     * @param $minutes
     * @return $this
     */
    public function everyMinutes($minutes)
    {
        return $this->everySeconds($minutes * 60);
    }

    /**
     * @param $name
     * @return $this
     */
    public function name($name)
    {
        $this->throttle->name = $name;

        return $this;
    }

    /**
     * @param $seconds
     * @return $this
     */
    public function retryAfterSeconds($seconds)
    {
        return $this->set('retryAfter', $seconds);
    }

    /**
     * @param $minutes
     * @return $this
     */
    public function retryAfterMinutes($minutes)
    {
        return $this->retryAfterSeconds($minutes * 60);
    }

    /**
     * @param $times
     * @return $this
     */
    public function retryMaxTimes($times)
    {
        return $this->setPayload('tries', $times);
    }

    /**
     * @param $time
     * @return $this
     */
    public function retryUntil($time)
    {
        return $this->setPayload('timeoutAt', $time);
    }

    /**
     * @param $seconds
     * @return $this
     */
    public function timeoutSeconds($seconds)
    {
        return $this->setPayload('timeout', $seconds);
    }

    // _________________________________________________________________________________________________________________

    /**
     * @return int|mixed
     */
    public function getMaxTries()
    {
        return $this->get('tries');
    }

    /**
     * @return int|mixed
     */
    public function getRetryDelay()
    {
        return $this->get('retryAfter') ?? $this->throttle->decay ?? 0;
    }

    // _________________________________________________________________________________________________________________

    /**
     * @param Queueable $job
     */
    public function applyConfig($job)
    {
        foreach ($this->config['payload'] as $param => $value) {
            $job->$param = $value;
        }
    }

    /**
     * @param $name
     * @return mixed
     */
    public function get($name)
    {
        return Arr::get($this->config, $name);
    }

    /**
     * @param $name
     * @param $value
     * @return $this
     */
    public function set($name, $value)
    {
        data_set($this->config, $name, $value);

        return $this;
    }

    /**
     * @return DurationLimiterBuilder|SyncThrottle
     */
    public function throttle()
    {
        return $this->throttle;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    protected function setPayload($key, $value)
    {
        return $this->set("payload.{$key}", $value);
    }
}