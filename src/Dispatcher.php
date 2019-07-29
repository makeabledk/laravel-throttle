<?php

namespace Makeable\Throttle;

use Illuminate\Support\Collection;
use Makeable\Throttle\Concerns\BuildsCallbacks;

/**
 * @mixin Configuration
 */
class Dispatcher
{
    use BuildsCallbacks;

    /**
     * @var Job
     */
    protected $job;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var string|null
     */
    protected $connectionName;

    /**
     * @var bool
     */
    protected $shouldExecute = true;

    /**
     * @param Job $job
     * @param null $connectionName
     */
    public function __construct($job, $connectionName = null)
    {
        $this->job = $job;
        $this->configuration = new Configuration(get_class($job), $this->isRedis());
        $this->connectionName = $connectionName;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->configuration->$name(...$arguments);
    }

    /**
     * A job fails after having been executed max amount of tries. If any
     * custom failed hooks has been registered we'll loop through them.
     *
     * A job will be marked as failed when
     *
     * - No custom failed hooks was specified
     * - A custom failed hook throws an exception
     *
     * @param \Exception|null $exception
     * @return $this
     */
    public function fail(\Exception $exception = null)
    {
        try {
            $this
                ->getCallbacks("failed")
                ->whenEmpty(function (Collection $callbacks) {
                    $callbacks->push(function ($exception) {
                        throw $exception;
                    });
                })
                ->each($this->invoke($exception, $this));
        }
        catch (\Exception $exception) {
            $this->job->fail($exception);
        }

        return $this;
    }

    /**
     * @param $seconds
     * @return $this
     */
    public function retry($seconds = null)
    {
        $this->job->release($seconds ?? $this->configuration->getRetryDelay());

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutExecution()
    {
        $this->shouldExecute = false;

        return $this;
    }

    /**
     * @param $callback
     * @return Throttle
     * @throws \Illuminate\Contracts\Redis\LimiterTimeoutException
     */
    protected function execute()
    {
        $this->configuration->throttle()->then(
            function () {
                try {
                    $this->getCallbacks('run')->each(function ($callback) {
                        call_user_func($callback, $this);
                    });
                } catch (\Exception $exception) {
                    $this->handleError($exception);
                }
            }
        );
    }

    /**
     * @param \Exception $exception
     * @return $this
     * @throws \Exception
     */
    protected function handleError(\Exception $exception)
    {
        try {
            $handled = $this
                ->getCallbacks('catch.'.get_class($exception))
                ->whenEmpty(function (Collection $callbacks) use ($exception) {
                    return $callbacks->concat($this->getCallbacks('catch.'.$exception->getCode()));
                })
                ->whenEmpty(function (Collection $callbacks) use ($exception) {
                    return $callbacks->concat($this->getCallbacks('catch.*'));
                })
                ->each($this->invoke($exception, $this));

            if ($handled->isNotEmpty()) {
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
    protected function isRedis()
    {
        return config("queue.connections.{$this->connectionName}.driver") === 'redis';
    }

    /**
     * @return bool
     */
    protected function shouldRetryFailedJob()
    {
        return $this->job->attempts() < $this->configuration->getMaxTries() && $this->isRedis();
    }
}
