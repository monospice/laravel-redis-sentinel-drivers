<?php

namespace Monospice\LaravelRedisSentinel\Tests\Unit;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Queue\RedisQueue as StandardRedisQueue;
use Illuminate\Redis\RedisManager;
use Laravel\Horizon\RedisQueue as HorizonRedisQueue;
use Monospice\LaravelRedisSentinel\Contracts\Factory as RedisSentinelFactory;
use Monospice\LaravelRedisSentinel\Manager\VersionedRedisSentinelManager;
use Monospice\LaravelRedisSentinel\RedisSentinelManager;
use Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider;
use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
use PHPUnit_Framework_TestCase as TestCase;

class RedisSentinelServiceProviderTest extends TestCase
{
    /**
     * An instance of the Laravel or Lumen application container.
     *
     * @var \Illuminate\Contracts\Container\Container
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

        $this->app->config->set(require(__DIR__ . '/../stubs/config.php'));

        $this->provider = new RedisSentinelServiceProvider($this->app);
    }

    public function testIsInitializable()
    {
        $this->assertInstanceOf(
            RedisSentinelServiceProvider::class,
            $this->provider
        );
    }

    public function testLoadsDefaultConfiguration()
    {
        $expectedConfigKeys = [
            'database.redis-sentinel',
            'database.redis.driver',
            'broadcasting.connections.redis-sentinel',
            'cache.stores.redis-sentinel',
            'queue.connections.redis-sentinel',
        ];

        $config = new ConfigRepository();
        $this->app->config = $config;

        // The package only sets "session.connection" when "session.driver"
        // equals "redis-sentinel"
        if (! ApplicationFactory::isLumen()) {
            $config->set('session.driver', 'redis-sentinel');
            $expectedConfigKeys[] = 'session.connection';
        }

        $this->provider->register();

        foreach ($expectedConfigKeys as $configKey) {
            $this->assertTrue($config->has($configKey), $configKey);
        }
    }

    public function testRegistersWithApplication()
    {
        $this->provider->register();

        $this->assertArrayHasKey('redis-sentinel', $this->app);

        $service = $this->app->make('redis-sentinel');

        $this->assertInstanceOf(RedisSentinelManager::class, $service);
        $this->assertInstanceOf(RedisFactory::class, $service);
        $this->assertInstanceOf(RedisSentinelFactory::class, $service);
    }

    public function testRegistersVersionedManagerWithApplication()
    {
        $this->provider->register();

        $this->assertArrayHasKey('redis-sentinel.manager', $this->app);

        $service = $this->app->make('redis-sentinel.manager');

        $this->assertInstanceOf(RedisFactory::class, $service);
        $this->assertInstanceOf(RedisSentinelFactory::class, $service);
    }

    public function testAliasesContractForInjection()
    {
        $this->provider->register();

        $this->assertArrayHasKey(RedisSentinelFactory::class, $this->app);

        $service = $this->app->make(RedisSentinelFactory::class);

        $this->assertInstanceOf(RedisSentinelManager::class, $service);
        $this->assertInstanceOf(RedisFactory::class, $service);
    }

    public function testRegisterPreservesStandardRedisApi()
    {
        $this->app->config->set('database.redis.driver', 'default');
        $this->provider->register();

        $redisService = $this->app->make('redis');

        $this->assertInstanceOf(RedisManager::class, $redisService);
        $this->assertNotInstanceOf(RedisSentinelManager::class, $redisService);
    }

    public function testRegisterOverridesStandardRedisApi()
    {
        $this->app->config->set('database.redis.driver', 'redis-sentinel');
        $this->provider->register();
        $this->provider->boot();

        $redisService = $this->app->make('redis');

        $this->assertInstanceOf(RedisSentinelManager::class, $redisService);
        $this->assertInstanceOf(RedisSentinelFactory::class, $redisService);
    }

    public function testRegistersHorizonServiceProvider()
    {
        $this->provider->register();
        $this->provider->boot();

        // We'll verify that the Horizon service provider booted by checking
        // whether the queue uses Horizon's classes:
        $connection = $this->app->queue->connection('redis-sentinel');

        $this->assertNotNull($connection);

        if (ApplicationFactory::isHorizonAvailable()
            && ! ApplicationFactory::isLumen()
        ) {
            $this->assertInstanceOf(HorizonRedisQueue::class, $connection);
        } else {
            $this->assertInstanceOf(StandardRedisQueue::class, $connection);
        }
    }

    public function testBootExtendsBroadcastConnections()
    {
        $this->provider->register();
        $this->provider->boot();

        $broadcast = $this->app->make(BroadcastFactory::class);

        $this->assertNotNull($broadcast->connection('redis-sentinel'));
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

    public function testWaitsForBoot()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->app->config->set('redis-sentinel.auto_boot', false);
        $this->provider->register();


        // It didn't auto boot
        $this->assertNull($this->app->cache->store('redis-sentinel'));
    }

    public function testAutoBoots()
    {
        $this->app->config->set('redis-sentinel.auto_boot', true);
        $this->provider->register();

        // Make sure it booted
        $this->assertNotNull($this->app->cache->store('redis-sentinel'));
    }
}
