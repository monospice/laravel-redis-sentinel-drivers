<?php

namespace Monospice\LaravelRedisSentinel\Tests\Integration;

use Monospice\LaravelRedisSentinel\RedisSentinelManager;
use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
use Monospice\LaravelRedisSentinel\Tests\Support\IntegrationTestCase;

class RedisSentinelManagerTest extends IntegrationTestCase
{
    /**
     * The instance of the manager subject under test
     *
     * @var RedisSentinelManager
     */
    protected $subject;

    /**
     * Run this setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $version = ApplicationFactory::getVersionedRedisSentinelManagerClass();
        $versionedManager = new $version('predis', $this->config);

        $this->subject = new RedisSentinelManager($versionedManager);
    }

    public function testExecutesRedisCommands()
    {
        // For this class, we only need to check that we can execute a Redis
        // command all the way down and back up the stack.
        $response = $this->subject->set('test-key', 'test value');

        $this->assertRedisResponseOk($response);
        $this->assertRedisKeyEquals('test-key', 'test value');
        $this->assertEquals('test value', $this->subject->get('test-key'));
    }

    public function testExecutesRedisCommandsOnSpecificConnection()
    {
        $connection = $this->subject->connection('default');
        $response = $connection->set('test-key', 'test value');

        $this->assertRedisResponseOk($response);
        $this->assertRedisKeyEquals('test-key', 'test value');
        $this->assertEquals('test value', $connection->get('test-key'));
    }
}
