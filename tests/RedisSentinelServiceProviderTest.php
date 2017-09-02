<?php

namespace Monospice\LaravelRedisSentinel\Tests;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Redis\Database as RedisDatabase;
use Monospice\LaravelRedisSentinel\RedisSentinelDatabase;
use Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider;
use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
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
        $this->app = ApplicationFactory::make();

        $this->app->config->set(require(__DIR__ . '/stubs/config.php'));

        $this->provider = new RedisSentinelServiceProvider($this->app);
    }

    public function testIsInitializable()
    {
        $class = 'Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider';

        $this->assertInstanceOf($class, $this->provider);
    }

    public function testLoadsDefaultConfiguration()
    {
        $config = new ConfigRepository();
        $this->app->config = $config;

        // The package only sets "session.connection" when "session.driver"
        // equals "redis-sentinel"
        if (! ApplicationFactory::isLumen()) {
            $config->set('session.driver', 'redis-sentinel');
        }

        $this->provider->register();

        $this->assertTrue($config->has('database.redis-sentinel'));
        $this->assertTrue($config->has('database.redis.driver'));
        $this->assertTrue($config->has('cache.stores.redis-sentinel'));
        $this->assertTrue($config->has('queue.connections.redis-sentinel'));

        if (! ApplicationFactory::isLumen()) {
            $this->assertTrue($config->has('session.connection'));
        }
    }

    public function testRegistersWithApplication()
    {
        $this->provider->register();

        $this->assertArrayHasKey('redis-sentinel', $this->app);

        $service = $this->app->make('redis-sentinel');
        $class = 'Monospice\LaravelRedisSentinel\RedisSentinelDatabase';
        $contract = 'Illuminate\Contracts\Redis\Database';

        $this->assertInstanceOf($class, $service);
        $this->assertInstanceOf($contract, $service);
    }

    public function testRegisterPreservesStandardRedisApi()
    {
        $this->app->config->set('database.redis.driver', 'default');
        $this->provider->register();

        $redisService = $this->app->make('redis');
        $class = 'Illuminate\Redis\Database';

        $this->assertInstanceOf($class, $redisService);
    }

    public function testRegisterOverridesStandardRedisApi()
    {
        $this->app->config->set('database.redis.driver', 'redis-sentinel');
        $this->provider->register();
        $this->provider->boot();

        $redisService = $this->app->make('redis');
        $class = 'Monospice\LaravelRedisSentinel\RedisSentinelDatabase';

        $this->assertInstanceOf($class, $redisService);
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

        if (ApplicationFactory::isLumen()) {
            $this->assertFalse($this->app->bound('session'));
        } else {
            $this->assertNotNull($this->app->session->driver('redis-sentinel'));
        }
    }
}
