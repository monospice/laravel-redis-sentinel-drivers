<?php

namespace Monospice\LaravelRedisSentinel\Manager;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Monospice\LaravelRedisSentinel\Configuration\Loader as ConfigurationLoader;
use Monospice\LaravelRedisSentinel\RedisSentinelManager;

/**
 * Creates instances of the core Redis Sentinel connection manager for the
 * current version of the framework.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     https://github.com/monospice/laravel-redis-sentinel-drivers
 */
class VersionedManagerFactory
{
    /**
     * The current application instance that Laravel's RedisManager depends on
     * in version 5.7+.
     *
     * @var Container
     */
    protected $app;

    /**
     * Detects the application version and provides configuration values.
     *
     * @var ConfigurationLoader
     */
    protected $config;

    /**
     * Create a factory using the provided configuration.
     *
     * @param Container           $app    The current application instance that
     * Laravel's RedisManager depends on in version 5.7+.
     * @param ConfigurationLoader $config Detects the application version and
     * provides configuration values.
     */
    public function __construct(Container $app, ConfigurationLoader $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Create an instance of the package's core Redis Sentinel service.
     *
     * @param Container           $app    The current application instance that
     * Laravel's RedisManager depends on in version 5.7+.
     * @param ConfigurationLoader $config Detects the application version and
     * provides configuration values.
     *
     * @return \Monospice\LaravelRedisSentinel\Contracts\Factory A configured
     * Redis Sentinel connection manager.
     */
    public static function make(Container $app, ConfigurationLoader $config)
    {
        return (new static($app, $config))->makeInstance();
    }

    /**
     * Create an instance of the package's core Redis Sentinel service.
     *
     * @return \Monospice\LaravelRedisSentinel\Contracts\Factory A configured
     * Redis Sentinel connection manager.
     */
    public function makeInstance()
    {
        $class = $this->getVersionedRedisSentinelManagerClass();
        $config = $this->config->get('database.redis-sentinel', [ ]);
        $driver = Arr::pull($config, 'client', 'predis');

        // Laravel 5.7 introduced the app as the first parameter:
        if ($this->appVersionLessThan('5.7')) {
            return new RedisSentinelManager(new $class($driver, $config));
        }

        return new RedisSentinelManager(new $class($this->app, $driver, $config));

    }

    /**
     * Get the fully-qualified class name of the RedisSentinelManager class
     * for the current version of Laravel or Lumen.
     *
     * @return string The class name of the appropriate RedisSentinelManager
     * with its namespace.
     */
    protected function getVersionedRedisSentinelManagerClass()
    {
        if ($this->config->isLumen) {
            $frameworkVersion = '5.4';
        } else {
            $frameworkVersion = '5.4.20';
        }

        if ($this->appVersionLessThan($frameworkVersion)) {
            return Laravel540RedisSentinelManager::class;
        }

        return Laravel5420RedisSentinelManager::class;
    }

    /**
     * Determine whether the current Laravel framework version is less than the
     * specified version.
     *
     * @param string $version The version to compare to the current version.
     *
     * @return bool TRUE current framework version is less than the specified
     * version.
     */
    protected function appVersionLessThan($version)
    {
        $appVersion = $this->config->getApplicationVersion();

        return version_compare($appVersion, $version, 'lt');
    }
}
