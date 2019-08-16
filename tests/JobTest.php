<?php

namespace Makeable\Throttle\Tests;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Makeable\Throttle\Job;
use Makeable\Throttle\Throttling;

class JobTest extends TestCase
{
    /** @test * */
    public function it_lol()
    {
//        echo get_class($this->job());
        $this->assertFalse(true);
    }
}

class Stub extends Job
{
    public function handle()
    {
        $this
            ->allow(500)
            ->everyMinutes(5)
            ->retryAfterMinutes(5)
            ->retryOnError(422)
            ->retryMaxTimes(3)
            ->run(function () {
                // Do something
            })
            ->catch(function (StripeException $exception) {
                // Catch specific exception
            })
            ->catch(function () {
                // Catch everything else
            })
            ->failed(function (Exception $exception) {
                // Handle some special failed logic here.
                // Job is only marked as failed if
                // exception is re-thrown here

                // throw $exception;
            });
    }
}