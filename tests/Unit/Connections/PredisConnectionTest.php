<?php

namespace Monospice\LaravelRedisSentinel\Tests\Connections\Unit;

use BadMethodCallException;
use Illuminate\Redis\Connections\Connection;
use Mockery;
use Monospice\LaravelRedisSentinel\Connections\PredisConnection;
use Monospice\SpicyIdentifiers\DynamicMethod;
use PHPUnit_Framework_TestCase as TestCase;
use Predis\ClientInterface;
use Predis\Connection\Aggregate\ReplicationInterface;
use Predis\Transaction\MultiExec;

class PredisConnectionTest extends TestCase
{
    /**
     * Sentinel-specific connection options and the values to test for.
     *
     * We set non-default values here so we can verify that the subject consumes
     * the new settings.
     *
     * @var array
     */
    protected static $sentinelOptions = [
        'sentinel_timeout' => 0.99,
        'retry_limit' => 99,
        'retry_wait' => 9999,
        'update_sentinels' => true,
    ];

    /**
     * The instance of the Predis client wrapper under test.
     *
     * @var PredisConnection
     */
    protected $subject;

    /**
     * A mock instance of the Predis Client used to verify calls made by the
     * connection under test.
     *
     * This test mocks the Predis client to verify behavior without actually
     * executing commands against a running Redis instance. This decouples the
     * unit test from Redis, but we cannot gauge accuracy of this implementation
     * without running the integration tests using the Predis client against a
     * real Redis server.
     *
     * @var Client
     */
    protected $clientMock;

    /**
     * Run this setup before each test.
     *
     * @return void
     */
    public function setUp()
    {
        $this->clientMock = Mockery::mock(ClientInterface::class);

        $this->subject = new PredisConnection($this->clientMock);
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
        $this->assertInstanceOf(PredisConnection::class, $this->subject);
    }

    public function testIsARedisConnection()
    {
        $this->assertInstanceOf(Connection::class, $this->subject);
    }

    public function testSetsSentinelConnectionOptionsFromConfig()
    {
        $sentinelOpts = static::$sentinelOptions;
        $connectionMock = $this->mockConnection();

        foreach ($sentinelOpts as $option => $value ) {
            $method = DynamicMethod::parseFromUnderscore($option);
            $connectionMock->shouldReceive($method->prepend('set')->name())
                ->with($value);
        }

        $this->subject = new PredisConnection($this->clientMock, $sentinelOpts);

        // This class provides no public interface to detect these values
        $this->assertAttributeEquals(99, 'retryLimit', $this->subject);
        $this->assertAttributeEquals(9999, 'retryWait', $this->subject);
    }

    public function testDisallowsInvalidSentinelOptions()
    {
        $this->setExpectedException(BadMethodCallException::class);

        new PredisConnection($this->clientMock, [ 'not_an_option' => null  ]);
    }

    public function testSetsSentinelOptionsFluentlyThroughApi()
    {
        $connectionMock = $this->mockConnection();

        foreach (static::$sentinelOptions as $option => $value) {
            $method = DynamicMethod::parseFromUnderscore($option);
            $connectionMock->shouldReceive($method->prepend('set')->name())
                ->with($value);

            $returnValue = $method->callOn($this->subject, [ $value ]);

            $this->assertSame($this->subject, $returnValue);
        }

        // This class provides no public interface to detect these values
        $this->assertAttributeEquals(99, 'retryLimit', $this->subject);
        $this->assertAttributeEquals(9999, 'retryWait', $this->subject);
    }

    public function testProvidesTransactionAbstraction()
    {
        $this->clientMock->shouldReceive('getClientFor')
            ->andReturn($this->clientMock);
        $this->clientMock->shouldReceive('transaction')
            ->andReturn(Mockery::mock(MultiExec::class));

        $predisTransaction = $this->subject->transaction();

        $this->assertInstanceOf(MultiExec::class, $predisTransaction);
    }

    /**
     * Set a mock Sentinel connection on the current Predis client mock.
     *
     * @param ReplicationInterface $mock An optional pre-configured mock.
     *
     * @return \Mockery\ExpectationInterface
     */
    protected function mockConnection(ReplicationInterface $mock = null)
    {
        if ($mock === null) {
            $mock = Mockery::mock(ReplicationInterface::class);
        }

        $this->clientMock->shouldReceive('getConnection')
            ->andReturn($mock);

        return $mock;
    }
}
