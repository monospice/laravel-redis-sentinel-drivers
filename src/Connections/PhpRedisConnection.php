<?php

namespace Monospice\LaravelRedisSentinel\Connections;

use Illuminate\Redis\Connections\PhpRedisConnection as LaravelPhpRedisConnection;
use Redis;
use RedisException;

/**
 * Executes Redis commands using the PhpRedis client.
 *
 * This package extends Laravel's PhpRedisConnection class to work around issues
 * experienced when using the PhpRedis client to send commands over "aggregate"
 * connections (in this case, Sentinel connections).
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   @pdbreen, Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     https://github.com/monospice/laravel-redis-sentinel-drivers
 */
class PhpRedisConnection extends LaravelPhpRedisConnection
{
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

        $this->retryLimit = (int) ($sentinelOptions['retry_limit'] ?? 20);
        $this->retryWait = (int) ($sentinelOptions['retry_wait'] ?? 1000);
    }

    /**
     * Execute commands in a transaction.
     *
     * @param  callable|null  $callback
     * @return \Redis|array
     */
    public function transaction(callable $callback = null)
    {
        return $this->retryOnFailure(function () use ($callback) {
            $transaction = $this->client()->multi();

            return is_null($callback)
                ? $transaction
                : tap($transaction, $callback)->exec();
        });
    }
    /**
     * Attempt to retry the provided operation when the client fails to connect
     * to a Redis server.
     *
     * We adapt Predis' Sentinel connection failure handling logic here to
     * reproduce the high-availability mode provided by the actual client. To
     * work around "aggregate" connection limitations in Predis, this class
     * provides methods that don't use the high-level Sentinel connection API
     * of Predis directly, so it needs to handle connection failures itself.
     *
     * @param callable $callback The operation to execute.
     *
     * @return mixed The result of the first successful attempt.
     *
     * @throws RedisException After exhausting the allowed number of
     * attempts to reconnect.
     */
    protected function retryOnFailure(callable $callback)
    {
        $attempts = 0;

        do {
            try {
                return $callback();
            } catch (RedisException $exception) {
                $this->client->close();

                usleep($this->retryWait * 1000);

                $this->client = $this->connector();

                $attempts++;
            }
        } while ($attempts <= $this->retryLimit);

        throw $exception;
    }
}
