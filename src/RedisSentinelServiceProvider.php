<?php

namespace Monospice\LaravelRedisSentinel;

use Illuminate\Broadcasting\Broadcasters\RedisBroadcaster;
use Illuminate\Cache\RedisStore;
use Illuminate\Queue\Connectors\RedisConnector;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Monospice\LaravelRedisSentinel\Configuration\Loader as ConfigurationLoader;
use Monospice\LaravelRedisSentinel\RedisSentinelDatabase;

/**
 * Registers the "redis-sentinel" driver as an available driver for Laravel's
 * cache, session, and queue services and loads the appropriate configuration.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     https://github.com/monospice/laravel-redis-sentinel-drivers
 */
class RedisSentinelServiceProvider extends ServiceProvider
{
    /**
     * Loads the package's configuration and provides configuration values.
     *
     * @var ConfigurationLoader
     */
    protected $config;

    /**
     * Boot the service by registering extensions with Laravel's cache, queue,
     * and session managers for the "redis-sentinel" driver.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootComponentDrivers();

        // If we want Laravel's Redis API to use Sentinel, we'll remove the
        // "redis" service from the deferred services in the container:
        if ($this->config->shouldOverrideLaravelRedisApi()) {
            $this->removeDeferredRedisServices();
        }
    }

    /**
     * Bind the "redis-sentinel" database driver to the application service
     * container.
     *
     * @return void
     */
    public function register()
    {
        $this->config = ConfigurationLoader::load($this->app);

        $this->app->singleton('redis-sentinel', function ($app) {
            $config = $app->make('config')->get('database.redis-sentinel', [ ]);

            return new RedisSentinelDatabase($config);
        });

        // If we want Laravel's Redis API to use Sentinel, we'll return an
        // instance of the RedisSentinelDatabase when requesting the "redis"
        // service:
        if ($this->config->shouldOverrideLaravelRedisApi()) {
            $this->registerOverrides();
        }
    }

    /**
     * Replace the standard Laravel Redis service with the Redis Sentinel
     * database driver so all Redis operations use Sentinel connections.
     *
     * @return void
     */
    protected function registerOverrides()
    {
        $this->app->singleton('redis', function ($app) {
            return $app->make('redis-sentinel');
        });
    }

    /**
     * Extend each of the Laravel services this package supports with the
     * corresponding 'redis-sentinel' driver.
     *
     * @return void
     */
    protected function bootComponentDrivers()
    {
        $this->addRedisSentinelCacheDriver();
        $this->addRedisSentinelQueueConnector();

        // The Laravel broadcasting API exists in version 5.1 and later, so we
        // will only register the broadcaster if available:
        if ($this->config->supportsBroadcasting) {
            $this->addRedisSentinelBroadcaster();
        }

        // Since version 5.2, Lumen does not include support for sessions by
        // default, so we'll only register the session handler if enabled:
        if ($this->config->supportsSessions) {
            $this->addRedisSentinelSessionHandler();
        }
    }

    /**
     * Remove the standard Laravel Redis service from the bound deferred
     * services so they don't overwrite Redis Sentinel registrations.
     *
     * @return void
     */
    protected function removeDeferredRedisServices()
    {
        if ($this->config->isLumen) {
            unset($this->app->availableBindings['redis']);

            return;
        }

        $deferredServices = $this->app->getDeferredServices();

        unset($deferredServices['redis']);

        $this->app->setDeferredServices($deferredServices);
    }

    /**
     * Add "redis-sentinel" as an available broadcaster option to the Laravel
     * event broadcasting manager.
     *
     * @return void
     */
    protected function addRedisSentinelBroadcaster()
    {
        $broadcast = 'Illuminate\Contracts\Broadcasting\Factory';

        // Lumen 5.2 and below don't provide a hook that initializes the
        // broadcast component when attempting to resolve the BroadcastManager:
        if ($this->config->isLumen
            && ! array_key_exists($broadcast, $this->app->availableBindings)
        ) {
            $provider = 'Illuminate\Broadcasting\BroadcastServiceProvider';
            $this->app->register($provider);
        }

        $this->app->make($broadcast)
            ->extend('redis-sentinel', function ($app, $conf) {
                $redis = $app->make('redis-sentinel');
                $connection = Arr::get($conf, 'connection', 'default');

                return new RedisBroadcaster($redis, $connection);
            });
    }

    /**
     * Add "redis-sentinel" as an available driver option to the Laravel cache
     * manager.
     *
     * @return void
     */
    protected function addRedisSentinelCacheDriver()
    {
        $cache = $this->app->make('cache');

        $cache->extend('redis-sentinel', function ($app, $conf) use ($cache) {
            $redis = $app->make('redis-sentinel');
            $prefix = $app->make('config')->get('cache.prefix');
            $connection = Arr::get($conf, 'connection', 'default');
            $store = new RedisStore($redis, $prefix, $connection);

            return $cache->repository($store);
        });
    }

    /**
     * Add "redis-sentinel" as an available driver option to the Laravel
     * session manager.
     *
     * @return void
     */
    protected function addRedisSentinelSessionHandler()
    {
        $this->app->make('session')->extend('redis-sentinel', function ($app) {
            $config = $app->make('config');
            $cacheDriver = clone $app->make('cache')->driver('redis-sentinel');
            $minutes = $config->get('session.lifetime');
            $connection = $config->get('session.connection');

            $cacheDriver->getStore()->setConnection($connection);

            return new CacheBasedSessionHandler($cacheDriver, $minutes);
        });
    }

    /**
     * Add "redis-sentinel" as an available queue connection driver option to
     * the Laravel queue manager.
     *
     * @return void
     */
    protected function addRedisSentinelQueueConnector()
    {
        $this->app->make('queue')->extend('redis-sentinel', function () {
            $redis = $this->app->make('redis-sentinel');

            return new RedisConnector($redis);
        });
    }
}
