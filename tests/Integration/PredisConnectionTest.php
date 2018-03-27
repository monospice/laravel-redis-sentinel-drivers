<?php

namespace Monospice\LaravelRedisSentinel\Tests\Integration;

use Mockery;
use Monospice\LaravelRedisSentinel\PredisConnection;
use Monospice\LaravelRedisSentinel\Tests\Support\DummyException;
use Monospice\LaravelRedisSentinel\Tests\Support\IntegrationTestCase;
use Predis\Client;
use Predis\Connection\ConnectionException;

class PredisConnectionTest extends IntegrationTestCase
{
    /**
     * The instance of the Predis client wrapper under test.
     *
     * @var PredisConnection
     */
    protected $subject;

    /**
     * Messages to publish and expect for subscribe tests.
     *
     * @var array
     */
    protected $expectedMessages = [
        'test-channel-1' => [ 'test message 1', 'test message 2', ],
        'test-channel-2' => [ 'test message 1', 'test message 2', ],
    ];

    /**
     * Run this setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->subject = $this->makeClientSpy();
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

    public function testProvidesBaseClientForNonSentinelConnections()
    {
        $childClass = 'Monospice\LaravelRedisSentinel\PredisConnection';

        $client = $this->subject->getClientFor('master');

        $this->assertNotInstanceOf($childClass, $client);
    }

    public function testAllowsSubscriptionsOnAggregateConnection()
    {
        // The Predis client itself does not currently support subscriptions on
        // Sentinel connections and throws an exception that this class fixes.
        $pubSub = $this->subject->pubSubLoop();

        $this->assertInstanceOf('Predis\PubSub\Consumer', $pubSub);
    }

    public function testSubscribesToPubSubChannels()
    {
        // Don't block the test with retries if it failed to read the expected
        // number of messages from the server:
        $this->subject->setRetryLimit(0);

        $received = [ ];

        $test = function ($channels, $count) use (&$received) {
            $this->subject->createSubscription(
                $channels,
                function ($message, $channel) use (&$received, &$count) {
                    $received[$channel][] = $message;

                    if (--$count === 0) {
                        return false;
                    }
                }
            );
        };

        $this->testClient->publishForTest($test, $this->expectedMessages);

        $this->assertEquals($this->expectedMessages, $received);
    }

    public function testSubscribesToPubSubChannelsByPattern()
    {
        // Don't block the test with retries if it failed to read the expected
        // number of messages from the server:
        $this->subject->setRetryLimit(0);

        $received = [ ];

        $test = function ($channels, $count) use (&$received) {
            $this->subject->createSubscription(
                'test-channel-*',
                function ($message, $channel) use (&$received, &$count) {
                    $received[$channel][] = $message;

                    if (--$count === 0) {
                        return false;
                    }
                },
                'psubscribe'
            );
        };

        $this->testClient->publishForTest($test, $this->expectedMessages);

        $this->assertEquals($this->expectedMessages, $received);
    }

    public function testSubscribesToPubSubChannelsUsingPredisApi()
    {
        // Don't block the test with retries if it failed to read the expected
        // number of messages from the server:
        $this->subject->setRetryLimit(0);

        $received = [ ];

        $test = function ($channels, $count) use (&$received) {
            $this->subject->pubSubLoop(
                [ 'subscribe' => $channels ],
                function ($loop, $message) use (&$received, &$count) {
                    if ($message->kind === 'message') {
                        $received[$message->channel][] = $message->payload;

                        if (--$count === 0) {
                            return false;
                        }
                    }
                }
            );
        };

        $this->testClient->publishForTest($test, $this->expectedMessages);

        $this->assertEquals($this->expectedMessages, $received);
    }

    public function testRetriesSubscriptionWhenConnectionFails()
    {
        $this->switchToMinimumTimeout();

        $expectedRetries = 2;
        $this->subject = $this->makeClientSpy();
        $this->subject->setRetryLimit($expectedRetries);
        $this->subject->setRetryWait(0); // retry immediately

        // With a read-write timeout, Predis throws a ConnectionException if
        // nothing publishes to the channel for the duration specified by the
        // timeout value. We'll use this with a low timeout to simulate a real
        // connection failure so we don't need to block a server manually.
        try {
            $this->subject->createSubscription([ 'channel' ], function () {
                return false;
            });
        } catch (ConnectionException $exception) {
            // With PHPUnit, we need to wrap the throwing block to perform
            // assertions afterward.
        }

        $this->subject->getConnection() // +1 for initial attempt:
            ->shouldHaveReceived('querySentinel')->times($expectedRetries + 1);
    }

    public function testSubscribesToSlaveByDefault()
    {
        $loop = $this->subject->pubSubLoop();
        $role = $loop->getClient()->executeRaw([ 'ROLE' ]);

        $this->assertEquals('slave', $role[0]);
    }

    public function testSubscribeFallsBackToMaster()
    {
        $this->subject->getConnection()
            ->shouldReceive('getSlaves')->andReturn([ ]);

        $loop = $this->subject->pubSubLoop();
        $role = $loop->getClient()->executeRaw([ 'ROLE' ]);

        $this->assertEquals('master', $role[0]);
    }

    public function testAllowsTransactionsOnAggregateConnection()
    {
        // The Predis client itself does not currently support transactions on
        // Sentinel connections and throws an exception that this class fixes.
        $transaction = $this->subject->transaction();

        $this->assertInstanceOf('Predis\Transaction\MultiExec', $transaction);
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

        $this->subject->setRetryLimit($expectedRetries);
        $this->subject->setRetryWait(0);  // retry immediately

        try {
            $this->subject->transaction(function () {
                $this->throwConnectionException();
            });
        } catch (ConnectionException $exception) {
            // With PHPUnit, we need to wrap the throwing block to perform
            // assertions afterward.
        }

        $this->subject->getConnection()->shouldHaveReceived('querySentinel')
            ->times($expectedRetries + 1); // increment for initial attempt
    }

    public function testCanReconnectWhenConnectionFails()
    {
        $retries = ceil(2 / $this->switchToMinimumTimeout()) + 1;
        $attempts = 0;

        $this->subject = $this->makeClientSpy();
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

        $this->subject->getConnection()
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
        // Yes, it's ugly--we're spying on the subject under test itself. We
        // need to do this to verify internal calls to methods of the parent
        // class of the subject (the Predis client). Unlike in the 2.x branch,
        // this PredisConnection implementation extends the Predis client
        // instead of consuming it as a dependency so we don't break backward
        // compatibility in Laravel 5.3 and below. The use of a partial mock
        // saves reams of code that we'd need to write to set up assertions
        // using Redis directly.
        //
        // Use caution when mocking methods on these spies. In most cases, we
        // just want to record that an internal method executed and pass the
        // call through to the real class.
        $clientSpy = Mockery::spy(
            'Monospice\LaravelRedisSentinel\PredisConnection',
            [
                $this->config['default'],
                $this->config['options'],
            ]
        )->makePartial();

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
        $nodeMock = Mockery::mock('Predis\Connection\NodeConnectionInterface');
        $nodeMock->shouldReceive('disconnect');

        throw new ConnectionException($nodeMock);
    }
}
