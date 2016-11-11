<?php

namespace Monospice\LaravelRedisSentinel\Tests;

use Closure;
use Monospice\LaravelRedisSentinel\RedisSentinelDatabase;
use PHPUnit_Framework_TestCase as TestCase;
use Predis\Connection\Aggregate\SentinelReplication;

class RedisSentinelDatabaseTest extends TestCase
{
    /**
     * The instance of the database driver subject under test
     *
     * @var RedisSentinelDatabase
     */
    protected $database;

    /**
     * Run this setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        $config = require(__DIR__ . '/stubs/config.php');
        $config = $config['database']['redis-sentinel'];

        $this->database = new RedisSentinelDatabase($config);
    }

    public function testIsInitializable()
    {
        $class = 'Monospice\LaravelRedisSentinel\RedisSentinelDatabase';

        $this->assertInstanceOf($class, $this->database);
    }

    public function testExtendsRedisDatabaseForSwapability()
    {
        $extends = 'Illuminate\Redis\Database';

        $this->assertInstanceOf($extends, $this->database);
    }

    public function testCreatesSentinelPredisClientsForEachConnection()
    {
        $client1 = $this->database->connection('connection1');
        $client2 = $this->database->connection('connection2');

        foreach ([ $client1, $client2 ] as $client) {
            $options = $client->getOptions();
            $replicationOption = $options->replication;

            $this->assertInstanceOf('Closure', $replicationOption);

            $expectedClass = 'Predis\Connection\Aggregate\SentinelReplication';
            $replicationClass = $replicationOption([], $options);

            $this->assertInstanceOf($expectedClass, $replicationClass);
        }
    }

    public function testSetsSentinelConnectionOptionsFromConfig()
    {
        $client1 = $this->database->connection('connection1');
        $client2 = $this->database->connection('connection2');

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
        $client1 = $this->database->connection('connection1');
        $client2 = $this->database->connection('connection2');

        foreach ([ $client1, $client2 ] as $client) {
            $expected = [ 'password' => 'secret', 'database' => 0 ];
            $parameters = $client->getOptions()->parameters;

            $this->assertEquals($expected, $parameters);
        }
    }

    public function testCreatesSingleClientsWithIndividualConfig()
    {
        $client1 = $this->database->connection('connection1');
        $client2 = $this->database->connection('connection2');

        $this->assertEquals('mymaster', $client1->getOptions()->service);
        $this->assertEquals('another-master', $client2->getOptions()->service);
    }
}
