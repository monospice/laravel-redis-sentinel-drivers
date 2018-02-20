<?php

namespace Monospice\LaravelRedisSentinel\Manager;

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
     * Detects the application version and provides configuration values.
     *
     * @var ConfigurationLoader
     */
    protected $config;

    /**
     * Create a factory using the provided configuration.
     *
     * @param ConfigurationLoader $config Detects the application version and
     * provides configuration values.
     */
    public function __construct(ConfigurationLoader $config)
    {
        $this->config = $config;
    }

    /**
     * Create an instance of the package's core Redis Sentinel service.
     *
     * @param ConfigurationLoader $config Detects the application version and
     * provides configuration values.
     *
     * @return \Monospice\LaravelRedisSentinel\Contracts\Factory A configured
     * Redis Sentinel connection manager.
     */
    public static function make(ConfigurationLoader $config)
    {
        return (new static($config))->makeInstance();
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

        return new RedisSentinelManager(new $class($driver, $config));
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
        $appVersion = $this->config->getApplicationVersion();

        if ($this->config->isLumen) {
            $frameworkVersion = '5.4';
        } else {
            $frameworkVersion = '5.4.20';
        }

        if (version_compare($appVersion, $frameworkVersion, 'lt')) {
            return Laravel540RedisSentinelManager::class;
        }

        return Laravel5420RedisSentinelManager::class;
    }
}
