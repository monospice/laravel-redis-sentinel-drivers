<?php

namespace Monospice\LaravelRedisSentinel\Tests\Integration;

use Monospice\LaravelRedisSentinel\RedisSentinelDatabase;
use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
use Monospice\LaravelRedisSentinel\Tests\Support\IntegrationTestCase;

class RedisSentinelDatabaseTest extends IntegrationTestCase
{
    /**
     * The instance of the database driver under test
     *
     * @var RedisSentinelDatabase
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

        $this->subject = new RedisSentinelDatabase($this->config);
    }

    public function testExecutesRedisCommands()
    {
        // Just check that we can execute a Redis command all the way down and
        // back up the stack.
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

    public function testSubscribesToPubSubChannels()
    {
        // Don't block the test with retries if it failed to read the expected
        // number of messages from the server:
        $this->subject->setRetryLimit(0);

        $received = [ ];

        $test = function ($channels, $count) use (&$received) {
            $this->subject->subscribe(
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
            $this->subject->psubscribe(
                'test-channel-*',
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
}
