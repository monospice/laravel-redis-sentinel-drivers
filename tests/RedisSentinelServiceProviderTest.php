<?php

namespace Monospice\LaravelRedisSentinel\Tests;

use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Foundation\Application;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Redis\RedisManager;
use Illuminate\Redis\RedisServiceProvider;
use Illuminate\Session\SessionServiceProvider;
use Monospice\LaravelRedisSentinel\RedisSentinelManager;
use Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider;
use PHPUnit_Framework_TestCase as TestCase;

class RedisSentinelServiceProviderTest extends TestCase
{
    /**
     * An instance of the Laravel application container
     *
     * @var Application
     */
    protected $app;

    /**
     * The instance of the Service Provider subject under test
     *
     * @var RedisSentinelServiceProvider
     */
    protected $provider;

    /**
     * Run this setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        $this->app = new Application();

        $this->app->config = new ConfigRepository(
            require(__DIR__ . '/stubs/config.php')
        );

        $this->app->register(new CacheServiceProvider($this->app));
        $this->app->register(new QueueServiceProvider($this->app));
        $this->app->register(new RedisServiceProvider($this->app));
        $this->app->register(new SessionServiceProvider($this->app));

        $this->provider = new RedisSentinelServiceProvider($this->app);
    }

    public function testIsInitializable()
    {
        $this->assertInstanceOf(
            RedisSentinelServiceProvider::class,
            $this->provider
        );
    }

    public function testRegistersWithApplication()
    {
        $this->provider->register();

        $this->assertArrayHasKey('redis-sentinel', $this->app);

        $service = $this->app->make('redis-sentinel');

        $this->assertInstanceOf(RedisSentinelManager::class, $service);
    }

    public function testRegisterPreservesStandardRedisApi()
    {
        $this->app->config->set('database.redis.driver', 'default');
        $this->provider->register();

        $redisService = $this->app->make('redis');

        $this->assertInstanceOf(RedisManager::class, $redisService);
    }

    public function testRegisterOverridesStandardRedisApi()
    {
        $this->provider->register();
        $this->provider->boot();

        $redisService = $this->app->make('redis');

        $this->assertInstanceOf(RedisSentinelManager::class, $redisService);
    }

    public function testBootExtendsCacheStores()
    {
        $this->provider->register();
        $this->provider->boot();

        $this->assertNotNull($this->app->cache->store('redis-sentinel'));
    }

    public function testBootExtendsQueueConnections()
    {
        $this->provider->register();
        $this->provider->boot();

        $this->assertNotNull($this->app->queue->connection('redis-sentinel'));
    }

    public function testBootExtendsSessionHandlers()
    {
        $this->provider->register();
        $this->provider->boot();

        $this->assertNotNull($this->app->session->driver('redis-sentinel'));
    }
}
