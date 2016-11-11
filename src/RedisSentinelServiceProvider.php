<?php

namespace Monospice\LaravelRedisSentinel;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RedisStore;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Connectors\RedisConnector;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Monospice\LaravelRedisSentinel\RedisSentinelDatabase;

/**
 * Registers the "redis-sentinel" driver as an available driver for Laravel's
 * cache, session, and queue services and loads the appropriate configuration.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     http://github.com/monospice/laravel-redis-sentinel-driver
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
        $this->app->singleton('redis-sentinel', function ($app) {
            $config = $app->make('config')->get('database.redis-sentinel');

            return new RedisSentinelDatabase($config);
        });

        // If we want Laravel's Redis API to use Sentinel, we'll return an
        // instance of the RedisSentinelDatabase when requesting the "redis"
        // service:
        if ($this->shouldOverrideLaravelApi()) {
            $this->app->singleton('redis', function ($app) {
                return $app->make('redis-sentinel');
            });
        }
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
            $redis = $app['redis-sentinel'];
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
        $app = $this->app;

        $queue->extend('redis-sentinel', function () use ($app) {
            return new RedisConnector($app->make('redis-sentinel'));
        });
    }

    /**
     * Determine this package should replace Laravel's Redis API ("Redis"
     * facade and "redis" service binding)
     *
     * @return bool True if "database.redis.driver" configuration option is
     * set to "sentinel"
     */
    protected function shouldOverrideLaravelApi()
    {
        $driver = $this->app->make('config')->get('database.redis.driver');

        return $driver === 'sentinel';
    }
}
