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
            ->tap()
            ->retryAfterMinutes(1)
            ->retryMaxTimes(1)
            ->retryOnError(422)
            ->run(function () {

            })
            ->catch(function () {

            });
    }
}