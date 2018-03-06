<?php

namespace Monospice\LaravelRedisSentinel\Tests\Integration\Connections;

use Mockery;
use Monospice\LaravelRedisSentinel\Connections\PredisConnection;
use Monospice\LaravelRedisSentinel\Tests\Support\DummyException;
use Monospice\LaravelRedisSentinel\Tests\Support\IntegrationTestCase;
use Predis\Client;
use Predis\Connection\ConnectionException;
use Predis\Connection\NodeConnectionInterface;
use Predis\Transaction\MultiExec;

class PredisConnectionTest extends IntegrationTestCase
{
    /**
     * The instance of the Predis client wrapper under test.
     *
     * @var PredisConnection
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

        $this->subject = new PredisConnection($this->makeClientSpy());
    }

    /**
     * Run this cleanup after each test.
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();

        Mockery::close();
    }

    public function testAllowsTransactionsOnAggregateConnection()
    {
        // The Predis client itself does not currently support transactions on
        // Sentinel connections and throws an exception that this class fixes.
        $predisTransaction = $this->subject->transaction();

        $this->assertInstanceOf(MultiExec::class, $predisTransaction);
    }

    public function testExecutesCommandsInTransaction()
    {
        $result = $this->subject->transaction(function ($trans) {
            $trans->set('test-key', 'test value');
            $trans->get('test-key');
        });

        $this->assertCount(2, $result);
        $this->assertRedisResponseOk($result[0]);
        $this->assertEquals('test value', $result[1]);
        $this->assertRedisKeyEquals('test-key', 'test value');
    }

    public function testExecutesTransactionsOnMaster()
    {
        $expectedSubset = [ 'Replication' => [ 'role' => 'master' ] ];

        $info = $this->subject->transaction(function ($transaction) {
            // Predis doesn't let us call "ROLE" from a transaction.
            $transaction->info();
        });

        $this->assertArraySubset($expectedSubset, $info[0]);
    }

    public function testAbortsTransactionOnException()
    {
        $exception = null;

        try {
            $this->subject->transaction(function ($trans) {
                $trans->set('test-key', 'test value');
                throw new DummyException();
            });
        } catch (DummyException $exception) {
            // With PHPUnit, we need to wrap the throwing block to perform
            // assertions afterward.
        }

        $this->assertNotNull($exception);
        $this->assertRedisKeyEquals('test-key', null);
    }

    public function testRetriesTransactionWhenConnectionFails()
    {
        $expectedRetries = 2;
        $clientSpy = $this->makeClientSpy();

        $this->subject = new PredisConnection($clientSpy, [
            'retry_limit' => $expectedRetries,
            'retry_wait' => 0, // retry immediately
        ]);

        try {
            $this->subject->transaction(function () {
                $this->throwConnectionException();
            });
        } catch (ConnectionException $exception) {
            // With PHPUnit, we need to wrap the throwing block to perform
            // assertions afterward.
        }

        $clientSpy->getConnection()->shouldHaveReceived('querySentinel')
            ->times($expectedRetries + 1); // increment for initial attempt
    }

    public function testCanReconnectWhenConnectionFails()
    {
        $retries = (2 / $this->switchToMinimumTimeout()) + 1;
        $attempts = 0;

        $this->subject = new PredisConnection($this->makeClientSpy());
        $this->subject->setRetryLimit((int) $retries);
        $this->subject->setRetryWait(0);  // retry immediately
        $this->subject->connect();

        // Do not block the master for more than the value of the Sentinel
        // down-after-milliseconds configuration directive or Sentinel will
        // initiate a failover:
        $this->testClient->blockMasterFor(2, function () use (&$attempts) {
            // Any retryable command re-implemented on the subject works, but
            // we'll use transaction for the callback:
            $this->subject->transaction(function ($trans) use (&$attempts) {
                $attempts++;
                $trans->set('test-key', 'test value');
            });
        });

        $this->subject->client()->getConnection()
            ->shouldHaveReceived('querySentinel')->atLeast()->once();

        $this->assertGreaterThan(1, $attempts, 'First try does not count.');
        $this->assertRedisKeyEquals('test-key', 'test value');
    }

    /**
     * Initialize a Predis client spy using the test connection configuration
     * that can verify connectivity failure handling.
     *
     * @return Client A client instance for the subject under test.
     */
    protected function makeClientSpy()
    {
        $clientSpy = Mockery::spy(new Client(
            $this->config['default'],
            $this->config['options']
        ));

        $connectionSpy = Mockery::spy($clientSpy->getConnection());

        $clientSpy->shouldReceive('getConnection')
            ->andReturn($connectionSpy)
            ->byDefault();

        return $clientSpy;
    }

    /**
     * Simulate a Predis connection exception.
     *
     * @return void
     *
     * @throws ConnectionException Such as would occur when the client cannot
     * connect to a Redis server.
     */
    protected function throwConnectionException()
    {
        $nodeMock = Mockery::mock(NodeConnectionInterface::class);
        $nodeMock->shouldReceive('disconnect');

        throw new ConnectionException($nodeMock);
    }
}
