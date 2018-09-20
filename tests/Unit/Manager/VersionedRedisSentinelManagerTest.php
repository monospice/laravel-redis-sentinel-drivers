<?php

namespace Monospice\LaravelRedisSentinel\Tests\Unit;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\RedisManager;
use InvalidArgumentException;
use Mockery;
use Monospice\LaravelRedisSentinel\Contracts\Factory as RedisSentinelFactory;
use Monospice\LaravelRedisSentinel\Manager\VersionedRedisSentinelManager;
use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
use PHPUnit_Framework_TestCase as TestCase;
use Predis\Connection\Aggregate\SentinelReplication;

class VersionedRedisSentinelManagerTest extends TestCase
{
    /**
     * The instance of the manager subject under test
     *
     * @var VersionedRedisSentinelManager
     */
    protected $subject;

    /**
     * Run this setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        $config = require(__DIR__ . '/../../stubs/config.php');
        $config = $config['database']['redis-sentinel'];

        $this->subject = $this->makeSubject('predis', $config);
    }

    public function testIsInitializable()
    {
        $this->assertInstanceOf(
            VersionedRedisSentinelManager::class,
            $this->subject
        );
    }

    public function testExtendsRedisManagerForSwapability()
    {
        $this->assertInstanceOf(RedisManager::class, $this->subject);
    }

    public function testIsRedisFactory()
    {
        $this->assertInstanceOf(RedisFactory::class, $this->subject);
    }

    public function testIsRedisSentinelFactory()
    {
        $this->assertInstanceOf(RedisSentinelFactory::class, $this->subject);
    }

    public function testCreatesSentinelPredisClientsForEachConnection()
    {
        $client1 = $this->subject->connection('default');
        $client2 = $this->subject->connection('connection2');

        foreach ([ $client1, $client2 ] as $client) {
            $options = $client->getOptions();
            $replicationOption = $options->replication;

            $this->assertInstanceOf('Closure', $replicationOption);

            $expectedClass = SentinelReplication::class;
            $replicationClass = $replicationOption([], $options);

            $this->assertInstanceOf($expectedClass, $replicationClass);
        }
    }

    public function testSetsSentinelConnectionOptionsFromConfig()
    {
        $client1 = $this->subject->connection('default');
        $client2 = $this->subject->connection('connection2');

        foreach ([ $client1, $client2 ] as $client) {
            $connection = $client1->getConnection();

            // Predis currently provides no way to detect these values through
            // a pubilc interface
            $this->assertAttributeEquals(0.99, 'sentinelTimeout', $connection);
            $this->assertAttributeEquals(99, 'retryLimit', $connection);
            $this->assertAttributeEquals(9999, 'retryWait', $connection);
            $this->assertAttributeEquals(true, 'updateSentinels', $connection);
        }
    }

    public function testCreatesSingleClientsWithSharedConfig()
    {
        $client1 = $this->subject->connection('default');
        $client2 = $this->subject->connection('connection2');

        foreach ([ $client1, $client2 ] as $client) {
            $expected = [ 'password' => 'secret', 'database' => 0 ];
            $parameters = $client->getOptions()->parameters;

            $this->assertEquals($expected, $parameters);
        }
    }

    public function testCreatesSingleClientsWithIndividualConfig()
    {
        $client1 = $this->subject->connection('default');
        $client2 = $this->subject->connection('connection2');

        $this->assertEquals('mymaster', $client1->getOptions()->service);
        $this->assertEquals('another-master', $client2->getOptions()->service);
    }

    public function testDisallowsRedisClusterConnections()
    {
        $this->setExpectedException(InvalidArgumentException::class);

        $this->subject->connection('clustered_connection');
    }

    public function testFailsOnUndefinedConnection()
    {
        $this->setExpectedException(InvalidArgumentException::class);

        $this->subject->connection('nonexistant_connection');
    }

    public function testFailsOnUnsupportedClientDriver()
    {
        $this->setExpectedException(InvalidArgumentException::class);

        $manager = $this->makeSubject('phpredis', [
            'test_connection' => [ ],
        ]);

        $manager->connection('test_connection');
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
            return new $class($client, $config);
        }

        // Laravel 5.7 introduced the app as the first parameter:
        return new $class(Mockery::mock(Container::class), $client, $config);
    }
}
