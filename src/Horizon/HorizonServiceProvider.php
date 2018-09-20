<?php

namespace Monospice\LaravelRedisSentinel\Horizon;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Connectors\RedisConnector as HorizonRedisConnector;
use Monospice\LaravelRedisSentinel\Configuration\Loader as ConfigurationLoader;
use Monospice\LaravelRedisSentinel\Horizon\HorizonServiceBindings;
use Monospice\LaravelRedisSentinel\Manager\VersionedManagerFactory;
use Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider;

/**
 * Configures the application to use Redis Sentinel connections for Laravel
 * Horizon.
 *
 * For applications that use Sentinel connections only for the queue API and
 * Horizon, we can register this service provider without the package's main
 * service provider.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     https://github.com/monospice/laravel-redis-sentinel-drivers
 */
class HorizonServiceProvider extends ServiceProvider
{
    /**
     * Loads the package's configuration and provides configuration values.
     *
     * @var ConfigurationLoader
     */
    protected $config;

    /**
     * Create a new service provider instance.
     *
     * @param Container           $app    The current Laravel/Lumen application
     * instance.
     * @param ConfigurationLoader $config Loads the package's configuration and
     * provides configuration values.
     */
    public function __construct(
        Container $app,
        ConfigurationLoader $config = null
    ) {
        parent::__construct($app);

        if ($config === null) {
            $config = ConfigurationLoader::load($app);
        }

        $this->config = $config;
    }

    /**
     * Set up any additional components needed for Horizon.
     *
     * @return void
     */
    public function boot()
    {
        if (! $this->config->shouldIntegrateHorizon) {
            return;
        }

        $this->addHorizonSentinelQueueConnector();
    }

    /**
     * Configure the package's services for use with Laravel Horizon.
     *
     * @return void
     */
    public function register()
    {
        if (! $this->config->shouldIntegrateHorizon) {
            return;
        }

        $this->config->loadHorizonConfiguration();

        $this->registerServices();

        if ($this->shouldRebindHorizonRedisFactory()) {
            $this->rebindHorizonRedisFactory();
        }
    }

    /**
     * Register the core Redis Sentinel connection manager if not already bound
     * (for using this service provider by itself).
     *
     * @return void
     */
    protected function registerServices()
    {
        $this->app->bindIf('redis-sentinel', function ($app) {
            return VersionedManagerFactory::make($this->app, $this->config);
        }, true);

        $this->app->bindIf('redis-sentinel.manager', function ($app) {
            return $app->make('redis-sentinel')->getVersionedManager();
        }, true);
    }

    /**
     * Determine whether the package needs to override the Redis service
     * injected into Horizon classes with the Sentinel service.
     *
     * @return bool True if configured as such and the package doesn't already
     * override the application's Redis API.
     */
    protected function shouldRebindHorizonRedisFactory()
    {
        // If we're using this package for Horizon only, we only register this
        // service provider, so nothing overrides Laravel's standard Redis API:
        if (! $this->app->bound(RedisSentinelServiceProvider::class)) {
            return true;
        }

        // If we're already overriding Laravel's standard Redis API, we don't
        // need to rebind the "redis" service for Horizon.
        return ! $this->config->shouldOverrideLaravelRedisApi;
    }

    /**
     * Add contextual bindings for Horizon's services that inject the package's
     * Redis Sentinel manager.
     *
     * @return void
     */
    protected function rebindHorizonRedisFactory()
    {
        // Although not all of the classes that Horizon registers need an
        // instance of the Redis service, we'll set up contextual bindings
        // for any declared so we don't need to update this package in the
        // future every time Horizon adds or removes one:
        foreach ((new HorizonServiceBindings()) as $serviceClass) {
            $this->app->when($serviceClass)
                ->needs(RedisFactory::class)
                ->give(function () {
                    return $this->app->make('redis-sentinel.manager');
                });
        }
    }

    /**
     * Add "redis-sentinel" as an available queue connection driver option to
     * the Laravel queue manager using Horizon's modified Redis connector.
     *
     * @return void
     */
    protected function addHorizonSentinelQueueConnector()
    {
        $this->app->make('queue')->extend('redis-sentinel', function () {
            $redis = $this->app->make('redis-sentinel.manager');

            return new HorizonRedisConnector($redis);
        });
    }
}
