<?php

namespace Makeable\Throttle;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * @mixin Dispatcher
 */
abstract class Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    abstract public function handle();

    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @param $name
     * @param $arguments
     * @return $this
     */
    public function __call($name, $arguments)
    {
        if (! $this->dispatcher) {
            $this->dispatcher = new Dispatcher($this, $this->getConnectionName());
        }

        $this->dispatcher->$name(...$arguments);

        return $this;
    }

    public function queue($queue, $command)
    {
        // Dry-run through configuration
        $this->withoutExecution()->handle();

        // Apply configuration on Laravel queue job
        $this->applyConfig($this);

        return $this->pushCommandToQueue($queue, $command);
    }

    /**
     * Push the command onto the given queue instance.
     *
     * Originally implemented in \Illuminate\Bus\Dispatcher
     *
     * @param  \Illuminate\Contracts\Queue\Queue  $queue
     * @param  mixed  $command
     * @return mixed
     */
    protected function pushCommandToQueue($queue, $command)
    {
        if (isset($command->queue, $command->delay)) {
            return $queue->laterOn($command->queue, $command->delay, $command);
        }

        if (isset($command->queue)) {
            return $queue->pushOn($command->queue, $command);
        }

        if (isset($command->delay)) {
            return $queue->later($command->delay, $command);
        }

        return $queue->push($command);
    }

    /**
     * @return string
     */
    public function getConnectionName()
    {
        return optional($this->job)->getConnectionName();
    }
}