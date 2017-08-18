<?php

namespace Monospice\LaravelRedisSentinel\Tests;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Monospice\LaravelRedisSentinel\Manager\VersionedRedisSentinelManager;
use Monospice\LaravelRedisSentinel\RedisSentinelManager;
use Mockery;
use PHPUnit_Framework_TestCase as TestCase;

class RedisSentinelManagerTest extends TestCase
{
    /**
     * The instance of the manager subject under test
     *
     * @var RedisSentinelManager
     */
    protected $manager;

    /**
     * A mock instance of the abstract VersionedRedisSentinelManager class
     *
     * @var VersionedRedisSentinelManager
     */
    protected $versionedManagerMock;

    /**
     * Run this setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        $this->versionedManagerMock
            = Mockery::mock(VersionedRedisSentinelManager::class);

        $this->manager = new RedisSentinelManager($this->versionedManagerMock);
    }

    /**
     * Run this cleanup after each test.
     *
     * @return void
     */
    public function tearDown()
    {
        Mockery::close();
    }

    public function testIsInitializable()
    {
        $this->assertInstanceOf(RedisSentinelManager::class, $this->manager);
    }

    public function testImplementsRedisFactoryForSwapability()
    {
        $this->assertInstanceOf(RedisFactory::class, $this->manager);
    }

    public function testGetsVersionedRedisSentinelManagerInstance()
    {
        $this->assertInstanceOf(
            VersionedRedisSentinelManager::class,
            $this->manager->getVersionedManager()
        );
    }

    public function testMakesARedisConnectionInstance()
    {
        $this->versionedManagerMock->shouldReceive("connection")
            ->with("connection1")
            ->andReturn(Mockery::mock(Connection::class));

        $connection = $this->manager->connection("connection1");

        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function testPassesMethodCallsToVersionedRedisSentinelManager()
    {
        $expectedValue = "expected value";

        // Pretend that we're executing the Redis "get" command:
        $this->versionedManagerMock->shouldReceive("get")
            ->with("someKey")
            ->andReturn($expectedValue);

        $this->assertEquals($this->manager->get("someKey"), $expectedValue);
    }
}
