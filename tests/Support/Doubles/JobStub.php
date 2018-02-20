<?php

namespace Monospice\LaravelRedisSentinel\Tests\Support\Doubles;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * A simple job that tests can push onto a queue.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     http://github.com/monospice/laravel-redis-sentinel-drivers
 */
class JobStub implements ShouldQueue
{
    /**
     * The allowed number of times to retry the job.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * Executed when processing the job.
     *
     * @return void
     */
    public function handle()
    {
    }
}
