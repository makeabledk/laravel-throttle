<?php

namespace Makeable\Throttle;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Redis\Limiters\DurationLimiterBuilder;
use Illuminate\Support\Facades\Redis;

/**
 * Class Throttle.
 *
 * @method Throttle allow($number)
 * @method Throttle every($seconds)
 */
class Throttle
{
    /**
     * The default amount of tries.
     *
     * @var int | null
     */
    public static $defaultTries;

    /**
     * The job instance.
     *
     * @var InteractsWithQueue
     */
    protected $job;

    /**
     * @var DurationLimiterBuilder
     */
    protected $throttle;

    /**
     * @var callable|null
     */
    protected $catchUsing;

    /**
     * @var callable|null
     */
    protected $failUsing;

    /**
     * Throttle constructor.
     *
     * @param $job
     * @param bool $supportsRedis
     */
    public function __construct($job, $supportsRedis = true)
    {
        $this->job = $job;

        if ($supportsRedis) {
            $this->throttle = Redis::throttle(get_class($job));
        }

        if ($this->job->throttleOptions === null) {
            $this->job->throttleOptions = [
                'jobConfig' => [
                    'tries' => static::$defaultTries ?? config('horizon.environments.'.app()->environment().'.supervisor-1.tries') ?? 3,
                ],
                'throttleConfig' => [],
            ];
        }

        $this->configureJob();
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return array_get($this->job->throttleOptions, $name);
    }

    /**
     * @param $name
     * @param $value
     * @return $this
     */
    public function __set($name, $value)
    {
        data_set($this->job->throttleOptions, $name, $value);

        return $this;
    }

    /**
     * @param $name
     * @param $arguments
     * @return $this
     */
    public function __call($name, $arguments)
    {
        if ($this->throttle) {
            $this->throttle->$name(...$arguments);
            $this->saveThrottleConfig();
        }

        return $this;
    }

    /**
     * @param $job
     * @param bool $supportsRedis
     * @return Throttle
     */
    public static function job(...$args)
    {
        return new static(...$args);
    }

    /**
     * @param $name
     * @return Throttle
     */
    public function name($name)
    {
        if ($this->throttle) {
            $this->throttle->name = $name;
            $this->saveThrottleConfig();
        }

        return $this;
    }

    /**
     * @param $seconds
     * @return Throttle
     */
    public function everySeconds($seconds)
    {
        $this->every($seconds);

        return $this;
    }

    /**
     * @param $minutes
     * @return Throttle
     */
    public function everyMinutes($minutes)
    {
        $this->every($minutes * 60);

        return $this;
    }

    /**
     * @param $seconds
     * @return Throttle
     */
    public function retryAfterSeconds($seconds)
    {
        $this->retryAfter = $seconds;

        return $this;
    }

    /**
     * @param $minutes
     * @return Throttle
     */
    public function retryAfterMinutes($minutes)
    {
        return $this->retryAfterSeconds($minutes * 60);
    }

    /**
     * @param $times
     * @return Throttle
     */
    public function retryMaxTimes($times)
    {
        return $this->configureJob('tries', $times);
    }

    /**
     * @param $time
     * @return Throttle
     */
    public function retryUntil($time)
    {
        return $this->configureJob('timeoutAt', $time);
    }

    /**
     * @param $seconds
     * @return Throttle
     */
    public function timeoutSeconds($seconds)
    {
        return $this->configureJob('timeout', $seconds);
    }

    /**
     * @param $callback
     * @return Throttle
     */
    public function catch($callback)
    {
        $this->catchUsing = $callback;

        return $this;
    }

    /**
     * @param $callback
     * @return Throttle
     */
    public function failed($callback)
    {
        $this->failUsing = $callback;

        return $this;
    }

    /**
     * @param $callback
     * @return Throttle
     * @throws \Illuminate\Contracts\Redis\LimiterTimeoutException
     */
    public function run($callback)
    {
        $this->throttle()->then(
            function () use ($callback) {
                try {
                    call_user_func($callback, $this);
                } catch (\Exception $exception) {
                    $this->handleError($exception);
                }
            }
        );

        return $this;
    }

    /**
     * @param \Exception|null $exception
     * @return $this
     */
    public function fail(\Exception $exception = null)
    {
        try {
            call_user_func($this->failUsing ?? function ($e) {
                    throw $e;
                }, $exception, $this);
        }
        catch (\Exception $exception) {
            $this->job->fail($exception);
        }

        return $this;
    }

    /**
     * @param $seconds
     * @return Throttle
     */
    public function retry($seconds = null)
    {
        $this->job->release(
            $seconds ?? $this->retryAfter ?? optional($this->throttle)->decay ?? 0
        );

        return $this;
    }

    /**
     * @param $configuration
     * @return Throttle
     */
    public function using($configuration)
    {
        $configuration($this);

        return $this;
    }

    // _________________________________________________________________________________________________________________

    /**
     * @return mixed
     */
    protected function maxTries()
    {
        return $this->job->tries;
    }

    /**
     * @param null $param
     * @param null $value
     * @return Throttle
     */
    protected function configureJob($param = null, $value = null)
    {
        if ($param !== null) {
            $this->__set('jobConfig.'.$param, $value);
        }

        foreach ($this->jobConfig as $param => $value) {
            $this->job->$param = $value;
        }

        return $this;
    }

    /**
     * @return Throttle
     */
    protected function loadThrottleConfig()
    {
        foreach ($this->throttleConfig as $param => $value) {
            $this->throttle->$param = $value;
        }

        return $this;
    }

    /**
     * @return Throttle
     */
    protected function saveThrottleConfig()
    {
        $config = array_except(get_object_vars($this->throttle), ['connection']);

        foreach ($config as $param => $value) {
            $this->__set('throttleConfig.'.$param, $value);
        }

        return $this;
    }

    /**
     * @return DurationLimiterBuilder|mixed
     */
    protected function throttle()
    {
        if (! $this->throttle || $this->loadThrottleConfig()->throttle->maxLocks === null) {
            return $this->immediateDispatching();
        }

        return $this->throttle;
    }

    /**
     * @return object
     */
    protected function immediateDispatching()
    {
        return new class {
            public function then($callback)
            {
                $callback();
            }
        };
    }

    /**
     * @param \Exception $exception
     * @return Throttle
     * @throws \Exception
     */
    protected function handleError(\Exception $exception)
    {
        try {
            if ($this->catchUsing) {
                call_user_func($this->catchUsing, $exception, $this);

                return $this;
            }
        } catch (\Exception $e) {
            //
        }

        if ($this->shouldRetryFailedJob()) {
            return $this->retry();
        }

        return $this->fail($exception);
    }

    /**
     * @return bool
     */
    protected function shouldRetryFailedJob()
    {
        return $this->job->attempts() < $this->maxTries() && $this->throttle;
    }
}
