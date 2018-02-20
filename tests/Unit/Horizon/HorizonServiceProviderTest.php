<?php

namespace Monospice\LaravelRedisSentinel\Tests\Unit\Horizon;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\RedisQueue as HorizonRedisQueue;
use Monospice\LaravelRedisSentinel\Configuration\Loader as ConfigurationLoader;
use Monospice\LaravelRedisSentinel\Contracts\Factory as RedisSentinelFactory;
use Monospice\LaravelRedisSentinel\Horizon\HorizonServiceProvider;
use Monospice\LaravelRedisSentinel\RedisSentinelManager;
use Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider;
use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
use PHPUnit_Framework_TestCase as TestCase;

class HorizonServiceProviderTest extends TestCase
{
    /**
     * The instance of the Service Provider subject under test
     *
     * @var HorizonServiceProvider
     */
    protected $subject;

    /**
     * An instance of the Laravel or Lumen application container.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $app;

    /**
     * Run this setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        $this->app = ApplicationFactory::make();
        $this->app->config->set(require(__DIR__ . '/../../stubs/config.php'));

        $config = ConfigurationLoader::load($this->app);

        $this->subject = new HorizonServiceProvider($this->app, $config);
    }

    /**
     * @group horizon
     */
    public function testIsInitializable()
    {
        $this->assertInstanceOf(HorizonServiceProvider::class, $this->subject);
    }

    /**
     * @group horizon
     */
    public function testIsInitializableByItself()
    {
        $this->subject = new HorizonServiceProvider($this->app);

        $this->assertInstanceOf(HorizonServiceProvider::class, $this->subject);
    }

    /**
     * @group horizon
     */
    public function testRegistersWithApplication()
    {
        $this->subject->register();

        $this->assertArrayHasKey('redis-sentinel', $this->app);

        $service = $this->app->make('redis-sentinel');

        $this->assertInstanceOf(RedisSentinelManager::class, $service);
        $this->assertInstanceOf(RedisFactory::class, $service);
        $this->assertInstanceOf(RedisSentinelFactory::class, $service);
    }

    /**
     * @group horizon
     */
    public function testRegistersVersionedManagerWithApplication()
    {
        $this->subject->register();

        $this->assertArrayHasKey('redis-sentinel.manager', $this->app);

        $service = $this->app->make('redis-sentinel.manager');

        $this->assertInstanceOf(RedisFactory::class, $service);
        $this->assertInstanceOf(RedisSentinelFactory::class, $service);
    }

    /**
     * @group horizon
     */
    public function testConfiguresHorizonConnection()
    {
        $this->subject->register();

        $connectionConfig = $this->app->config->get('database.redis-sentinel');

        $this->assertArrayHasKey('horizon', $connectionConfig);
    }

    /**
     * @group horizon
     */
    public function testRebindsRedisFactoryForHorizonImplicitly()
    {
        $this->app->config->set('database.redis.driver', 'redis-sentinel');
        $this->app->config->set('horizon.driver', 'default');
        $this->app->register(RedisSentinelServiceProvider::class);
        $this->subject->register();

        ApplicationFactory::configureHorizonComponents($this->app);
        $repo = $this->app->make(JobRepository::class);

        $this->assertInstanceOf(RedisSentinelFactory::class, $repo->redis);
    }

    /**
     * @group horizon
     */
    public function testRebindsRedisFactoryForHorizonExplicitly()
    {
        $this->app->config->set('database.redis.driver', 'default');
        $this->app->config->set('horizon.driver', 'redis-sentinel');
        $this->app->register(RedisSentinelServiceProvider::class);
        $this->subject->register();

        ApplicationFactory::configureHorizonComponents($this->app);
        $repo = $this->app->make(JobRepository::class);

        $this->assertInstanceOf(RedisSentinelFactory::class, $repo->redis);
    }

    /**
     * @group horizon
     */
    public function testRebindsRedisFactoryForHorizonExplicitlyByItself()
    {
        $this->app->config->set('horizon.driver', 'redis-sentinel');
        $this->subject->register();

        ApplicationFactory::configureHorizonComponents($this->app);
        $repo = $this->app->make(JobRepository::class);

        $this->assertInstanceOf(RedisSentinelFactory::class, $repo->redis);
    }

    /**
     * @group horizon
     */
    public function testBootExtendsQueueConnectionsWithHorizonConnector()
    {
        $this->subject->register();
        $this->subject->boot();

        $queue = $this->app->queue->connection('redis-sentinel');

        $this->assertInstanceOf(HorizonRedisQueue::class, $queue);
    }
}
