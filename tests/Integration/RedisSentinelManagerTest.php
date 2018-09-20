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

        $this->subject = $this->makeSubject('predis', $this->config);
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

    /**
     * Create an instance of the subject under test.
     *
     * @param string $client The name of the Redis client implementation.
     * @param array  $config A set of connection manager config values.
     *
     * @return VersionedRedisSentinelManager The correct version of the
     * Sentinel connection manager for the current version of Laravel.
     */
    protected function makeSubject($client, array $config)
    {
        $class = ApplicationFactory::getVersionedRedisSentinelManagerClass();
        $version = ApplicationFactory::getApplicationVersion();

        if (version_compare($version, '5.7', 'lt')) {
            return new RedisSentinelManager(new $class($client, $config));
        }

        // Laravel 5.7 introduced the app as the first parameter:
        return new RedisSentinelManager(
            new $class(ApplicationFactory::make(), $client, $config)
        );
    }
}
