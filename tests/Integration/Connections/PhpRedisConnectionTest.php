<?php

namespace Monospice\LaravelRedisSentinel\Tests\Integration\Connections;

use Monospice\LaravelRedisSentinel\Connections\PhpRedisConnection;
use Monospice\LaravelRedisSentinel\Connectors\PhpRedisConnector;
use Monospice\LaravelRedisSentinel\Tests\Support\DummyException;
use Monospice\LaravelRedisSentinel\Tests\Support\IntegrationTestCase;
use Monospice\LaravelRedisSentinel\Exceptions\RedisRetryException;
use Redis;
use RedisException;

class PhpRedisConnectionTest extends IntegrationTestCase
{
    /**
     * The instance of the PhpRedis client wrapper under test.
     *
     * @var PhpRedisConnection
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

        if (! extension_loaded('redis')) {
            return;
        }

        $this->subject = $this->makeConnection();
    }

    public function testAllowsTransactionsOnAggregateConnection()
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The redis extension is not installed. Please install the extension to enable '.__CLASS__);

            return;
        }

        $transaction = $this->subject->transaction();

        $this->assertInstanceOf(Redis::class, $transaction);
    }

    public function testExecutesCommandsInTransaction()
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The redis extension is not installed. Please install the extension to enable '.__CLASS__);

            return;
        }

        $result = $this->subject->transaction(function ($trans) {
            $trans->set('test-key', 'test value');
            $trans->get('test-key');
        });

        $this->assertCount(2, $result);
        $this->assertTrue($result[0]);
        $this->assertEquals('test value', $result[1]);
        $this->assertRedisKeyEquals('test-key', 'test value');
    }

    public function testExecutesTransactionsOnMaster()
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The redis extension is not installed. Please install the extension to enable '.__CLASS__);

            return;
        }

        $expectedSubset = ['role' => 'master'];

        $info = $this->subject->transaction(function ($transaction) {
            $transaction->info();
        });

        $this->assertArraySubset($expectedSubset, $info[0]);
    }

    public function testAbortsTransactionOnException()
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The redis extension is not installed. Please install the extension to enable '.__CLASS__);

            return;
        }

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
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The redis extension is not installed. Please install the extension to enable '.__CLASS__);

            return;
        }

        $this->expectException(RedisRetryException::class);

        $this->subject = $this->makeConnection(1, 0); // retry once and immediately

        $this->subject->transaction(function () {
            throw new RedisException();
        });
    }

    public function testCanReconnectWhenConnectionFails()
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The redis extension is not installed. Please install the extension to enable '.__CLASS__);

            return;
        }

        $retries = 3;
        $attempts = 0;

        $this->subject = $this->makeConnection($retries, 0); // retry immediately

        $this->subject->transaction(function ($trans) use (&$attempts, $retries) {
            $attempts++;

            if ($attempts < $retries) {
                throw new RedisException();
            } else {
                $trans->set('test-key', 'test value');
            }
        });

        $this->assertGreaterThan(1, $attempts, 'First try does not count.');
        $this->assertRedisKeyEquals('test-key', 'test value');
    }

    /**
     * Initialize a PhpRedis client using the test connection configuration
     * that can verify connectivity failure handling.
     *
     * @param int|null $retryLimit
     * @param int|null $retryWait
     * @return PhpRedisConnection A client instance for the subject under test.
     */
    protected function makeConnection(int $retryLimit = null, int $retryWait = null)
    {
        $connector = new PhpRedisConnector();

        $options = $this->config['options'];
        if ($retryLimit !== null) {
            $options['retry_limit'] = $retryLimit;
        }

        if ($retryWait !== null) {
            $options['retry_wait'] = $retryWait;
        }

        return $connector->connect($this->config['default'], $options);
    }
}
