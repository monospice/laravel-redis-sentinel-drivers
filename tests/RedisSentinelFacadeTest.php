<?php

namespace Monospice\LaravelRedisSentinel\Tests;

use Mockery;
use Monospice\LaravelRedisSentinel\RedisSentinel;
use Monospice\LaravelRedisSentinel\RedisSentinelManager;
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
        $service = RedisSentinelManager::class;
        $app = ApplicationFactory::make();

        $app->singleton('redis-sentinel', function () use ($service) {
            return Mockery::mock($service);
        });

        RedisSentinel::setFacadeApplication($app);

        $this->assertInstanceOf($service, RedisSentinel::getFacadeRoot());
    }
}
