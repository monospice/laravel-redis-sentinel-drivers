<?php

namespace Monospice\LaravelRedisSentinel\Tests\Integration\Connections;

use Monospice\LaravelRedisSentinel\Connections\PhpRedisConnection;
use Monospice\LaravelRedisSentinel\Connectors\PhpRedisConnector;
use Monospice\LaravelRedisSentinel\Tests\Support\IntegrationTestCase;
use Monospice\LaravelRedisSentinel\Exceptions\RedisRetryException;

class PhpRedisConnectorTest extends IntegrationTestCase
{
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
    }

    public function testCanConnect()
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The redis extension is not installed. Please install the extension to enable '.__CLASS__);

            return;
        }

        $connector = new PhpRedisConnector();
        $client = $connector->connect($this->config['default'], $this->config['options']);

        $this->assertInstanceOf(PhpRedisConnection::class, $client);
    }

    public function testRetriesTransactionWhenConnectionFails()
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The redis extension is not installed. Please install the extension to enable '.__CLASS__);

            return;
        }

        $this->expectException(RedisRetryException::class);

        $connector = new PhpRedisConnector();

        $servers = [
            [
                'host' => '127.0.0.1',
                'port' => 1111,
            ],
        ];

        $options = array_merge([
            'connector_retry_limit' => 3,
            'connector_retry_wait' => 0,
        ]);

        $connector = new PhpRedisConnector();
        $connector->connect($servers, $options);
    }
}
