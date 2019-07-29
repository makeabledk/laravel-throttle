<?php

namespace Makeable\Throttle\Tests;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Makeable\Throttle\Throttling;

class ThrottleConfigTest extends TestCase
{
    /** @test * */
    public function it_stores_configuration_on_the_queue_job()
    {
        dispatch($job = new ExampleJob_1());

        dd(unserialize(serialize($job)));

        $this->assertEquals(5, $job->throttleOptions['jobConfig']['tries']);
        $this->assertEquals(5, $job->throttleOptions['retryAfter']);
        $this->assertEquals(5, $job->tries);
    }
}

class ExampleJob_1 extends \Makeable\Throttle\Job {
    public function handle()
    {
        $this
            ->retryAfterSeconds(5)
            ->retryMaxTimes(5)
            ->retryOnError(422)
            ->run(function () {

            });
    }
}