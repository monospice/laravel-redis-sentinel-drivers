<?php

namespace Monospice\LaravelRedisSentinel\Connections;

use Closure;
use Illuminate\Redis\Connections\PhpRedisConnection as LaravelPhpRedisConnection;
use Monospice\LaravelRedisSentinel\Connectors\PhpRedisConnector;
use Redis;
use RedisException;

/**
 * Executes Redis commands using the PhpRedis client.
 *
 * This package extends Laravel's PhpRedisConnection class to wrap all command
 * methods with a retryOnFailure method.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Jeffrey Zant <j.zant@slash2.nl>
 * @license  See LICENSE file
 * @link     https://github.com/monospice/laravel-redis-sentinel-drivers
 */
class PhpRedisConnection extends LaravelPhpRedisConnection
{
    /**
     * The connection creation callback.
     *
     * Laravel 5 does not store the connector by default.
     *
     * @var callable|null
     */
    protected $connector;

    /**
     * The number of times the client attempts to retry a command when it fails
     * to connect to a Redis instance behind Sentinel.
     *
     * @var int
     */
    protected $retryLimit = 20;

    /**
     * The time in milliseconds to wait before the client retries a failed
     * command.
     *
     * @var int
     */
    protected $retryWait = 1000;

    /**
     * Create a new PhpRedis connection.
     *
     * @param  \Redis  $client
     * @param  callable|null  $connector
     * @param  array  $sentinelOptions
     * @return void
     */
    public function __construct($client, callable $connector = null, array $sentinelOptions = [])
    {
        parent::__construct($client, $connector);

        // Set the connector when it is not set. Used for Laravel 5.
        if (! $this->connector) {
            $this->connector = $connector;
        }

        // Set the retry limit.
        if (isset($sentinelOptions['retry_limit']) && is_numeric($sentinelOptions['retry_limit'])) {
            $this->retryLimit = (int) $sentinelOptions['retry_limit'];
        }

        // Set the retry wait.
        if (isset($sentinelOptions['retry_wait']) && is_numeric($sentinelOptions['retry_wait'])) {
            $this->retryWait = (int) $sentinelOptions['retry_wait'];
        }
    }

    /**
     * {@inheritdoc} in addition retry on client failure.
     *
     * @param  mixed  $cursor
     * @param  array  $options
     * @return mixed
     */
    public function scan($cursor, $options = [])
    {
        return $this->retryOnFailure(function () use ($cursor, $options) {
            return parent::scan($cursor, $options);
        });
    }

    /**
     * {@inheritdoc} in addition retry on client failure.
     *
     * @param  string  $key
     * @param  mixed  $cursor
     * @param  array  $options
     * @return mixed
     */
    public function zscan($key, $cursor, $options = [])
    {
        return $this->retryOnFailure(function () use ($key, $cursor, $options) {
            parent::zscan($key, $cursor, $options);
        });
    }

    /**
     * {@inheritdoc} in addition retry on client failure.
     *
     * @param  string  $key
     * @param  mixed  $cursor
     * @param  array  $options
     * @return mixed
     */
    public function hscan($key, $cursor, $options = [])
    {
        return $this->retryOnFailure(function () use ($key, $cursor, $options) {
            parent::hscan($key, $cursor, $options);
        });
    }

    /**
     * {@inheritdoc} in addition retry on client failure.
     *
     * @param  string  $key
     * @param  mixed  $cursor
     * @param  array  $options
     * @return mixed
     */
    public function sscan($key, $cursor, $options = [])
    {
        return $this->retryOnFailure(function () use ($key, $cursor, $options) {
            parent::sscan($key, $cursor, $options);
        });
    }

    /**
     * {@inheritdoc} in addition retry on client failure.
     *
     * @param  callable|null  $callback
     * @return \Redis|array
     */
    public function pipeline(callable $callback = null)
    {
        return $this->retryOnFailure(function () use ($callback) {
            return parent::pipeline($callback);
        });
    }

    /**
     * {@inheritdoc} in addition retry on client failure.
     *
     * @param  callable|null  $callback
     * @return \Redis|array
     */
    public function transaction(callable $callback = null)
    {
        return $this->retryOnFailure(function () use ($callback) {
            return parent::transaction($callback);
        });
    }

    /**
     * {@inheritdoc} in addition retry on client failure.
     *
     * @param  array|string  $channels
     * @param  \Closure  $callback
     * @return void
     */
    public function subscribe($channels, Closure $callback)
    {
        return $this->retryOnFailure(function () use ($channels, $callback) {
            return parent::subscribe($channels, $callback);
        });
    }

    /**
     * {@inheritdoc} in addition retry on client failure.
     *
     * @param  array|string  $channels
     * @param  \Closure  $callback
     * @return void
     */
    public function psubscribe($channels, Closure $callback)
    {
        return $this->retryOnFailure(function () use ($channels, $callback) {
            return parent::psubscribe($channels, $callback);
        });
    }

    /**
     * {@inheritdoc} in addition retry on client failure.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function command($method, array $parameters = [])
    {
        return $this->retryOnFailure(function () use ($method, $parameters) {
            return parent::command($method, $parameters);
        });
    }

    /**
     * Attempt to retry the provided operation when the client fails to connect
     * to a Redis server.
     *
     * @param callable $callback The operation to execute.
     * @return mixed The result of the first successful attempt.
     */
    protected function retryOnFailure(callable $callback)
    {
        return PhpRedisConnector::retryOnFailure($callback, $this->retryLimit, $this->retryWait, function () {
            $this->client->close();

            try {
                if ($this->connector) {
                    $this->client = call_user_func($this->connector);
                }
            } catch (RedisException $e) {
                // Ignore when the creation of a new client gets an exception.
                // If this exception isn't caught the retry will stop.
            }
        });
    }
}
