<?php

namespace Monospice\LaravelRedisSentinel\Tests;

use Closure;
use Illuminate\Redis\RedisManager;
use InvalidArgumentException;
use Monospice\LaravelRedisSentinel\RedisSentinelManager;
use PHPUnit_Framework_TestCase as TestCase;
use Predis\Connection\Aggregate\SentinelReplication;

class RedisSentinelManagerTest extends TestCase
{
    /**
     * The instance of the manager subject under test
     *
     * @var RedisSentinelManager
     */
    protected $manager;

    /**
     * Run this setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        $config = require(__DIR__ . '/stubs/config.php');
        $config = $config['database']['redis-sentinel'];

        $this->manager = new RedisSentinelManager('predis', $config);
    }

    public function testIsInitializable()
    {
        $this->assertInstanceOf(RedisSentinelManager::class, $this->manager);
    }

    public function testExtendsRedisManagerForSwapability()
    {
        $this->assertInstanceOf(RedisManager::class, $this->manager);
    }

    public function testCreatesSentinelPredisClientsForEachConnection()
    {
        $client1 = $this->manager->connection('connection1');
        $client2 = $this->manager->connection('connection2');

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
        $client1 = $this->manager->connection('connection1');
        $client2 = $this->manager->connection('connection2');

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
        $client1 = $this->manager->connection('connection1');
        $client2 = $this->manager->connection('connection2');

        foreach ([ $client1, $client2 ] as $client) {
            $expected = [ 'password' => 'secret', 'database' => 0 ];
            $parameters = $client->getOptions()->parameters;

            $this->assertEquals($expected, $parameters);
        }
    }

    public function testCreatesSingleClientsWithIndividualConfig()
    {
        $client1 = $this->manager->connection('connection1');
        $client2 = $this->manager->connection('connection2');

        $this->assertEquals('mymaster', $client1->getOptions()->service);
        $this->assertEquals('another-master', $client2->getOptions()->service);
    }

    public function testDisallowsRedisClusterConnections()
    {
        $this->setExpectedException(InvalidArgumentException::class);

        $this->manager->connection('clustered_connection');
    }

    public function testFailsOnUndefinedConnection()
    {
        $this->setExpectedException(InvalidArgumentException::class);

        $this->manager->connection('nonexistant_connection');
    }

    public function testFailsOnUnsupportedClientDriver()
    {
        $this->setExpectedException(InvalidArgumentException::class);

        $manager = new RedisSentinelManager('phpredis', [
            'test_connection' => [ ],
        ]);

        $manager->connection('test_connection');
    }
}
