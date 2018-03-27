<?php

namespace Monospice\LaravelRedisSentinel\Tests\Unit;

use Mockery;
use Monospice\LaravelRedisSentinel\RedisSentinel;
use Monospice\LaravelRedisSentinel\RedisSentinelDatabase;
use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
use PHPUnit_Framework_TestCase as TestCase;

class RedisSentinelFacadeTest extends TestCase
{
    /**
     * Run this cleanup after each test.
     *
     * @return void
     */
    public function tearDown()
    {
        Mockery::close();
    }

    public function testResolvesFacadeServiceFromContainer()
    {
        $service = 'Monospice\LaravelRedisSentinel\RedisSentinelDatabase';
        $app = ApplicationFactory::make();

        $app->singleton('redis-sentinel', function () use ($service) {
            return Mockery::mock($service);
        });

        RedisSentinel::setFacadeApplication($app);

        $this->assertInstanceOf($service, RedisSentinel::getFacadeRoot());
    }
}
