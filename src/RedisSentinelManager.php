<?php

namespace Monospice\LaravelRedisSentinel;

use Illuminate\Redis\RedisManager;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Monospice\LaravelRedisSentinel\Connectors;

/**
 * Enables Laravel's Redis database driver to accept configuration options for
 * Redis Sentinel connections independently.
 *
 * By default, Laravel's Redis service permits a single set of configuration
 * options for all of the Redis connections passed to the Predis client. This
 * prevents us from declaring separate parameters for individual Redis services
 * managed by Sentinel. For example, we may wish to connect to a separate Redis
 * Sentinel service cluster set, or use a separate Redis database, for caching
 * queues, and sessions. This wrapper class enables us to declare parameters
 * for each connection in the "redis-sentinel" block of the database
 * configuration which it will use to configure individual clients.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     http://github.com/monospice/laravel-redis-sentinel-drivers
 */
class RedisSentinelManager extends RedisManager
{
    /**
     * Get the Redis Connection instance represented by the specified name
     *
     * @param string $name The name of the connection as defined in the
     * configuration
     *
     * @return \Illuminate\Redis\Connections\PredisConnection The configured
     * Redis Connection instance
     *
     * @throws InvalidArgumentException If attempting to initialize a Redis
     * Cluster connection
     * @throws InvalidArgumentException If the specified connection is not
     * defined in the configuration
     */
    public function resolve($name = null)
    {
        $options = Arr::get($this->config, 'options', [ ]);

        if (isset($this->config[$name])) {
            return $this->connector()->connect($this->config[$name], $options);
        }

        if (isset($this->config['clusters']['name'])) {
            throw new InvalidArgumentException(
                'Redis Sentinel connections do not support Redis Cluster.'
            );
        }

        throw new InvalidArgumentException(
            'The Redis Sentinel connection [' . $name . '] is not defined.'
        );
    }

    /**
     * Get the appropriate Connector instance for the current client driver
     *
     * @return Connectors\PredisConnector The Connector instance for the
     * current driver
     *
     * @throws InvalidArgumentException If the current client driver is not
     * supported
     */
    protected function connector()
    {
        switch ($this->driver) {
            case 'predis':
                return new Connectors\PredisConnector();
        }

        throw new InvalidArgumentException(
            'Unsupported Redis Sentinel client driver [' . $this->driver . ']. '
            . 'The monospice/laravel-redis-sentinel-drivers package currently '
            . 'supports only the "predis" client. Support for the "phpredis" '
            . 'client will be added in the future.'
        );
    }
}
