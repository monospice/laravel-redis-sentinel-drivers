<?php

namespace Monospice\LaravelRedisSentinel;

use Monospice\LaravelRedisSentinel\Contracts\Factory;
use Monospice\LaravelRedisSentinel\Manager\VersionedRedisSentinelManager;
use Monospice\SpicyIdentifiers\DynamicMethod;

/**
 * Enables Laravel's Redis database driver to accept configuration options for
 * Redis Sentinel connections independently. Replaces Laravel's RedisManager
 * for Redis Sentinel connections.
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
 * Laravel changed the public interface of the RedisManager class in version
 * 5.4.20. Because this package extends the functionality of that class, the
 * implementation needed to change to match the modified interface. To avoid
 * frivolous package versioning and simplify installation and upgrade through
 * composer, this class handles any small differences between minor Laravel
 * versions by wrapping implementations of RedisSentinelManager for each
 * diverging Laravel version.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     http://github.com/monospice/laravel-redis-sentinel-drivers
 */
class RedisSentinelManager implements Factory
{
    /**
     * The RedisSentinelManager instance for the current version of Laravel
     *
     * @var VersionedRedisSentinelManager
     */
    private $versionedManager;

    /**
     * Wrap the provided RedisSentinelManager instance that manages Sentinel
     * connections for the current version of Laravel.
     *
     * @param VersionedRedisSentinelManager $versionedManager Manages Redis
     * Sentinel connections for the current version of Laravel
     */
    public function __construct(VersionedRedisSentinelManager $versionedManager)
    {
        $this->versionedManager = $versionedManager;
    }

    /**
     * Get the current RedisSentinelManager instance that manages Sentinel
     * connections for the current version of Laravel.
     *
     * @return VersionedRedisSentinelManager The versioned implementation of
     * RedisSentinelManager
     */
    public function getVersionedManager()
    {
        return $this->versionedManager;
    }

    /**
     * Get a Redis Sentinel connection by name.
     *
     * @param string|null $name The name of the connection as defined in the
     * application's configuration
     *
     * @return \Illuminate\Redis\Connections\Connection The requested Redis
     * Sentinel connection
     */
    public function connection($name = null)
    {
        return $this->versionedManager->connection($name);
    }

    /**
     * Pass method calls to the RedisSentinelManager instance for the current
     * version of Laravel.
     *
     * @param string $methodName The name of the called method
     * @param array  $arguments  The arguments passed to the called method
     *
     * @return mixed The return value from the underlying method
     */
    public function __call($methodName, array $arguments)
    {
        return DynamicMethod::from($methodName)
            ->callOn($this->versionedManager, $arguments);
    }
}
