<?php

namespace Monospice\LaravelRedisSentinel\Tests\Support;

use Exception;
use Monospice\LaravelRedisSentinel\Tests\Support\PubSubReader;
use Monospice\LaravelRedisSentinel\Tests\Support\TestClient;
use PHPUnit_Framework_TestCase as TestCase;
use Predis\Client;
use Predis\Response\Status as ResponseStatus;

/**
 * Provides support, configuration, and clean-up when testing against running
 * Sentinel servers.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     https://github.com/monospice/laravel-redis-sentinel-drivers
 */
abstract class IntegrationTestCase extends TestCase
{
    /**
     * The "redis-sentinel" connection configuration to use for integration
     * testing that wraps the settings declared in phpunit.xml.
     *
     * @var array
     */
    protected $config;

    /**
     * A Predis Client wrapper that connects to the same Sentinel servers as
     * the classes under test for behavior verification and test clean-up.
     *
     * @var Client
     */
    protected $testClient;

    /**
     * Stores the message to display when skipping an integration test because
     * of a problem with the test environment.
     *
     * @var string|bool
     */
    private $skipTestReason;

    /**
     * Run this setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        $this->skipIntegrationTestUnlessConfigured();
        $this->configureTest();

        $this->testClient->ping();

        $this->testClient->publish('>>>> STARTING TEST', $this->getFullName());
    }

    /**
     * Run this cleanup after each test.
     *
     * @return void
     */
    public function tearDown()
    {
        $this->testClient->publish('>>>> TEST FINISHED', $this->getFullName());

        $this->testClient->flushdb();
    }

    /**
     * Assert that Redis responded that a command executed successfully.
     *
     * @param mixed $response The response returned by the client from Redis.
     *
     * @return void
     */
    public function assertRedisResponseOk($response)
    {
        $message = 'For Redis response:';
        $this->assertInstanceOf(ResponseStatus::class, $response, $message);

        $payload = $response->getPayload();
        $this->assertEquals('OK', $payload, $message);
    }

    /**
     * Assert that the provided value equals the value at the specified key
     * in Redis.
     *
     * @param string $key      The key of the value in Redis to compare.
     * @param mixed  $expected The value that should exist for the key.
     *
     * @return void
     */
    public function assertRedisKeyEquals($key, $expected)
    {
        $actual = $this->testClient->get($key);
        $message = "For Redis key: $key";

        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * Assert that the number of items in the Redis list at the specified key
     * equals the provided count.
     *
     * @param string $key      The key of the list in Redis to compare
     * @param int    $expected The number of items that the list should contain.
     *
     * @return void
     */
    public function assertRedisListCount($key, $expected)
    {
        $actual = $this->testClient->llen($key);
        $message = "For Redis list at key: $key";

        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * Assert that the number of items in the Redis sorted set at the specified
     * key equals the provided count.
     *
     * @param string $key      The key of the sorted set in Redis to compare
     * @param int    $expected The number of items that the set should contain.
     *
     * @return void
     */
    public function assertRedisSortedSetCount($key, $expected)
    {
        $actual = $this->testClient->zcard($key);
        $message = "For Redis sorted set: $key";

        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * Assert that the specified message(s) are published to the corresponding
     * Redis channel(s) when executing the provided callback.
     *
     * @param array   $expectedMessages Two-dimensional array keyed by channel
     * names. Each contains an array of message strings expected on the channel.
     * @param Closure $callback         Publishes the expected messages.
     *
     * @return void
     */
    public function assertPublishes(array $expectedMessages, callable $callback)
    {
        $reader = new PubSubReader($this->testClient->getClientFor('master'));

        $this->assertEquals($expectedMessages, $reader->capture(
            array_keys($expectedMessages),  // channels
            count(call_user_func_array('array_merge', $expectedMessages)),
            $callback
        ));
    }

    /**
     * Use the configured minimum timeout value for tests cases that verify
     * failure handling behavior.
     *
     * @return float The minimum timeout value itself.
     */
    public function switchToMinimumTimeout()
    {
        return $this->config['options']['parameters']['read_write_timeout']
            = TEST_MIN_CONNECTION_TIMEOUT;
    }

    /**
     * Read test environment configuration values provided by phpunit.xml and
     * initalize a supporting test client.
     *
     * @return void
     */
    private function configureTest()
    {
        $sentinels = explode(',', TEST_REDIS_SENTINEL_HOST);

        $options = [
            'service' => TEST_REDIS_SENTINEL_SERVICE,
            'replication' => 'sentinel',
            'parameters' => [
                'database' => TEST_REDIS_DATABASE,
                'timeout' => TEST_MAX_CONNECTION_TIMEOUT,
                'read_write_timeout' => TEST_MAX_CONNECTION_TIMEOUT,
            ],
        ];

        $this->config = [
            'default' => $sentinels,
            'options' => $options,
        ];

        $this->testClient = new TestClient($sentinels, $options);
    }

    /**
     * Mark an integration test as "skipped" if the phpunit configuration does
     * not provide a valid set of connection settings.
     *
     * @return void
     */
    private function skipIntegrationTestUnlessConfigured()
    {
        if ($this->skipTestReason === false) {
            return;
        }

        if ($this->skipTestReason !== null) {
            $this->markTestSkipped($this->skipTestReason);
        }

        if (! defined('TEST_REDIS_SENTINEL_HOST')) {
            $this->skipBecause('No Sentinel hosts configured.');
        }

        if (! defined('TEST_REDIS_SENTINEL_SERVICE')) {
            $this->skipBecause('No Sentinel service configured.');
        }

        if (! defined('TEST_REDIS_DATABASE')) {
            $this->skipBecause('No Redis database number configured.');
        }

        if (! defined('TEST_MAX_CONNECTION_TIMEOUT')) {
            $this->skipBecause('No maximum connection timeout configured.');
        }

        if (! defined('TEST_MIN_CONNECTION_TIMEOUT')) {
            $this->skipBecause('No minimum connection timeout configured.');
        }

        $this->skipTestReason = false;
    }

    /**
     * Mark an integration test as "skipped" for the provided reason.
     *
     * @param string $reason Describes why we're skipping the test.
     *
     * @return void
     */
    private function skipBecause($reason)
    {
        $this->skipTestReason = $reason;

        $this->markTestSkipped($reason);
    }

    /**
     * Get the name of the current test to publish in Redis.
     *
     * @return string The integration test namespace, class name, and test
     * method name.
     */
    private function getFullName()
    {
        return '...' . substr(get_class($this), 48) . '::' . $this->getName();
    }
}
