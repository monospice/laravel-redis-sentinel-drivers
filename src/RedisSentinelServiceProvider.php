<?php

namespace Monospice\LaravelRedisSentinel;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RedisStore;
use Illuminate\Foundation\Application;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Connectors\RedisConnector;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Monospice\LaravelRedisSentinel\RedisSentinelManager;
use Monospice\LaravelRedisSentinel\Manager;

/**
 * Registers the "redis-sentinel" driver as an available driver for Laravel's
 * cache, session, and queue services and loads the appropriate configuration.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     http://github.com/monospice/laravel-redis-sentinel-drivers
 */
class RedisSentinelServiceProvider extends ServiceProvider
{
    /**
     * Boot the service by registering extensions with Laravel's cache, queue,
     * and session managers for the "redis-sentinel" driver.
     *
     * @return void
     */
    public function boot()
    {
        $this->addRedisSentinelCacheDriver($this->app->make('cache'));
        $this->addRedisSentinelSessionHandler($this->app->make('session'));
        $this->addRedisSentinelQueueConnector($this->app->make('queue'));

        // If we want Laravel's Redis API to use Sentinel, we'll remove the
        // "redis" service from the list of deferred services in the container:
        if ($this->shouldOverrideLaravelApi()) {
            $deferredServices = $this->app->getDeferredServices();

            unset($deferredServices['redis']);
            unset($deferredServices['redis.connection']);

            $this->app->setDeferredServices($deferredServices);
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
        $class = $this->getVersionedRedisSentinelManagerClass();

        $this->app->singleton('redis-sentinel', function ($app) use ($class) {
            $config = $app->make('config')->get('database.redis-sentinel');
            $driver = Arr::pull($config, 'client', 'predis');

            return new RedisSentinelManager(new $class($driver, $config));
        });

        // If we want Laravel's Redis API to use Sentinel, we'll return an
        // instance of the RedisSentinelManager when requesting the "redis"
        // service:
        if ($this->shouldOverrideLaravelApi()) {
            $this->registerOverrides();
        }
    }

    /**
     * Replace the standard Laravel Redis service with the Redis Sentinel
     * database driver so all redis operations use Sentinel connections.
     *
     * @return void
     */
    protected function registerOverrides()
    {
        $this->app->singleton('redis', function ($app) {
            return $app->make('redis-sentinel');
        });

        $this->app->bind('redis.connection', function ($app) {
            return $app->make('redis-sentinel')->connection();
        });
    }

    /**
     * Add "redis-sentinel" as an available driver option to the Laravel cache
     * manager.
     *
     * @param CacheManager $cache The Laravel cache manager
     *
     * @return void
     */
    protected function addRedisSentinelCacheDriver(CacheManager $cache)
    {
        $cache->extend('redis-sentinel', function ($app, $conf) use ($cache) {
            $redis = $app->make('redis-sentinel')->getVersionedManager();
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
     * @param SessionManager $session The Laravel session manager
     *
     * @return void
     */
    protected function addRedisSentinelSessionHandler(SessionManager $session)
    {
        $session->extend('redis-sentinel', function ($app) {
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
     * @param QueueManager $queue The Laravel queue manager
     *
     * @return void
     */
    protected function addRedisSentinelQueueConnector(QueueManager $queue)
    {
        $queue->extend('redis-sentinel', function () {
            $redis = $this->app->make('redis-sentinel')->getVersionedManager();

            return new RedisConnector($redis);
        });
    }

    /**
     * Determine whether this package should replace Laravel's Redis API
     * ("Redis" facade and "redis" service binding).
     *
     * @return bool True if "database.redis.driver" configuration option is
     * set to "sentinel"
     */
    protected function shouldOverrideLaravelApi()
    {
        $driver = $this->app->make('config')->get('database.redis.driver');

        return $driver === 'sentinel';
    }

    /**
     * Get the fully-qualified class name of the RedisSentinelManager class
     * for the current version of Laravel.
     *
     * @return string The class name of the appropriate RedisSentinelManager
     * with its namespace
     */
    protected function getVersionedRedisSentinelManagerClass()
    {
        if (version_compare(Application::VERSION, '5.4.20', 'lt')) {
            return Manager\Laravel540RedisSentinelManager::class;
        }

        return Manager\Laravel5420RedisSentinelManager::class;
    }
}
