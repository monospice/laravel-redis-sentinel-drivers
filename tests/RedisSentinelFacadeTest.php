<?php

namespace Monospice\LaravelRedisSentinel\Tests;

use Mockery;
use Monospice\LaravelRedisSentinel\RedisSentinel;
use Monospice\LaravelRedisSentinel\RedisSentinelDatabase;
use PHPUnit_Framework_TestCase as TestCase;

class RedisSentinelFacadeTest extends TestCase
{
    public function testResolvesFacadeServiceFromContainer()
    {
        $service = 'Monospice\LaravelRedisSentinel\RedisSentinelDatabase';

        $app = new \Illuminate\Foundation\Application();
        $app->singleton('redis-sentinel', function () use ($service) {
            return Mockery::mock($service);
        });

        RedisSentinel::setFacadeApplication($app);

        $this->assertInstanceOf($service, RedisSentinel::getFacadeRoot());
    }
}
