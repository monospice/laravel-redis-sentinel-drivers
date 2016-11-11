<?php

namespace Monospice\LaravelRedisSentinel\Tests;

use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Redis\Database as RedisDatabase;
use Illuminate\Redis\RedisServiceProvider;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Str;
use Monospice\LaravelRedisSentinel\RedisSentinelDatabase;
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

        // For running tests against Laravel Framework < 5.3, we need to set
        // up encryption to boot the Queue services
        if (Application::VERSION < 5.3) {
            if (Application::VERSION < 5.2) {
                $testKey = Str::random(16);
                $cipher = MCRYPT_RIJNDAEL_128;
            } else {
                $testKey = 'base64:' . base64_encode(random_bytes(16));
                $cipher = 'AES-128-CBC';
            }

            $this->app->config->set('app.key', $testKey);
            $this->app->config->set('app.cipher', $cipher);

            $this->app->register(new EncryptionServiceProvider($this->app));
        }

        $this->provider = new RedisSentinelServiceProvider($this->app);
    }

    public function testIsInitializable()
    {
        $class = 'Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider';

        $this->assertInstanceOf($class, $this->provider);
    }

    public function testRegistersWithApplication()
    {
        $this->provider->register();

        $this->assertArrayHasKey('redis-sentinel', $this->app);

        $service = $this->app->make('redis-sentinel');
        $class = 'Monospice\LaravelRedisSentinel\RedisSentinelDatabase';

        $this->assertInstanceOf($class, $service);
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

        $this->assertNotNull($this->app->session->driver('redis-sentinel'));
    }
}
