<?php

namespace Monospice\LaravelRedisSentinel;

use Illuminate\Broadcasting\Broadcasters\RedisBroadcaster;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Queue\Connectors\RedisConnector;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Monospice\LaravelRedisSentinel\Configuration\Loader as ConfigurationLoader;
use Monospice\LaravelRedisSentinel\Contracts\Factory;
use Monospice\LaravelRedisSentinel\Horizon\HorizonServiceProvider;
use Monospice\LaravelRedisSentinel\Manager\VersionedManagerFactory;

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
     * Records whether this provider has already been booted (eg. via auto-boot)
     *
     * @var boolean
     */
    private $isBooted = false;

    /**
     * Boot the service by registering extensions with Laravel's cache, queue,
     * and session managers for the "redis-sentinel" driver.
     *
     * @return void
     */
    public function boot()
    {
        // If we configured the package to boot its services immediately after
        // the registration phase (auto-boot), don't boot the provider again:
        if ($this->isBooted) {
            return;
        }

        $this->bootComponentDrivers();

        // If we want Laravel's Redis API to use Sentinel, we'll remove the
        // "redis" service from the deferred services in the container:
        if ($this->config->shouldOverrideLaravelRedisApi) {
            $this->removeDeferredRedisServices();
        }

        if ($this->config->shouldIntegrateHorizon) {
            $horizon = new HorizonServiceProvider($this->app, $this->config);
            $horizon->register();
            $horizon->boot();
        }

        $this->isBooted = true;
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

        $this->registerServices();

        // If we want Laravel's Redis API to use Sentinel, we'll return an
        // instance of the RedisSentinelManager when requesting the "redis"
        // service:
        if ($this->config->shouldOverrideLaravelRedisApi) {
            $this->registerOverrides();
        }

        // If we explicitly configured the package to auto-boot, run the boot
        // phase now to bind the packages drivers. This overcomes issues with
        // other third-party packages that don't follow Laravel's convention:
        if ($this->config->shouldAutoBoot()) {
            $this->boot();
        }
    }

    /**
     * Register the core Redis Sentinel connection manager.
     *
     * @return void
     */
    protected function registerServices()
    {
        $this->app->singleton('redis-sentinel', function () {
            return VersionedManagerFactory::make($this->app, $this->config);
        });

        $this->app->singleton('redis-sentinel.manager', function ($app) {
            return $app->make('redis-sentinel')->getVersionedManager();
        });

        $this->app->alias('redis-sentinel', Factory::class);
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

        $this->app->bind('redis.connection', function ($app) {
            return $app->make('redis-sentinel.manager')->connection();
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
        $this->addRedisSentinelBroadcaster();
        $this->addRedisSentinelCacheStore();

        // This package's Horizon service provider will set up the queue
        // connector a bit differently, so we don't need to do it twice:
        if (! $this->config->shouldIntegrateHorizon) {
            $this->addRedisSentinelQueueConnector();
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
            return;
        }

        $deferredServices = $this->app->getDeferredServices();

        unset($deferredServices['redis']);
        unset($deferredServices['redis.connection']);

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
        $this->app->make(BroadcastFactory::class)
            ->extend('redis-sentinel', function ($app, $conf) {
                $redis = $app->make('redis-sentinel.manager');
                $connection = Arr::get($conf, 'connection', 'default');

                return new RedisBroadcaster($redis, $connection);
            });
    }

    /**
     * Add "redis-sentinel" as an available cache store option to the Laravel
     * cache manager.
     *
     * @return void
     */
    protected function addRedisSentinelCacheStore()
    {
        $cache = $this->app->make('cache');

        $cache->extend('redis-sentinel', function ($app, $conf) use ($cache) {
            $redis = $app->make('redis-sentinel.manager');
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
            $cacheDriver = clone $app->make('cache')->driver('redis-sentinel');
            $minutes = $this->config->get('session.lifetime');
            $connection = $this->config->get('session.connection');

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
            $redis = $this->app->make('redis-sentinel.manager');

            return new RedisConnector($redis);
        });
    }
}
