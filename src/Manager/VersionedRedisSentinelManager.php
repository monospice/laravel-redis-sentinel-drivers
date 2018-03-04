<?php

namespace Monospice\LaravelRedisSentinel\Manager;

use Illuminate\Redis\RedisManager;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Monospice\LaravelRedisSentinel\Connectors;
use Monospice\LaravelRedisSentinel\Contracts\Factory;

/**
 * Contains common functionality for the RedisSentinelManager implementations
 * for differing Laravel versions.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     http://github.com/monospice/laravel-redis-sentinel-drivers
 */
abstract class VersionedRedisSentinelManager
    extends RedisManager
    implements Factory
{
    /**
     * Get the Redis Connection instance represented by the specified name
     *
     * @param string|null $name The name of the connection as defined in the
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
    protected function resolveConnection($name = null)
    {
        $name = $name ?: 'default';
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
