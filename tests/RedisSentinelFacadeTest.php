<?php

namespace Monospice\LaravelRedisSentinel\Tests;

use Illuminate\Foundation\Application;
use Mockery;
use Monospice\LaravelRedisSentinel\RedisSentinel;
use Monospice\LaravelRedisSentinel\RedisSentinelManager;
use PHPUnit_Framework_TestCase as TestCase;

class RedisSentinelFacadeTest extends TestCase
{
    public function testResolvesFacadeServiceFromContainer()
    {
        $service = RedisSentinelManager::class;

        $app = new Application();
        $app->singleton('redis-sentinel', function () use ($service) {
            return Mockery::mock($service);
        });

        RedisSentinel::setFacadeApplication($app);

        $this->assertInstanceOf($service, RedisSentinel::getFacadeRoot());
    }
}
