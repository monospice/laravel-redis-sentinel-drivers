<?php

namespace Monospice\LaravelRedisSentinel\Tests\Integration\Drivers;

use Illuminate\Cache\RedisStore;
use Illuminate\Session\CacheBasedSessionHandler;
use Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider;
use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
use Monospice\LaravelRedisSentinel\Tests\Support\IntegrationTestCase;

class SessionTest extends IntegrationTestCase
{
    /**
     * The instance of the Redis Sentinel queue connector under test.
     *
     * @var Illuminate\Contracts\Queue\Queue
     */
    protected $subject;

    /**
     * Run this setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $app = ApplicationFactory::make();
        $app->config->set(require(__DIR__ . '/../../stubs/config.php'));
        $app->config->set('database.redis-sentinel', $this->config);
        $app->register(RedisSentinelServiceProvider::class);
        $app->boot();

        $this->subject = $app->session->driver('redis-sentinel');
    }

    /**
     * @group laravel-only
     */
    public function testIsBackedByARedisCacheStore()
    {
        $handler = $this->subject->getHandler();
        $this->assertInstanceOf(CacheBasedSessionHandler::class, $handler);

        $cacheStore = $handler->getCache()->getStore();
        $this->assertInstanceOf(RedisStore::class, $cacheStore);
    }

    /**
     * @group laravel-only
     */
    public function testFetchesSessionData()
    {
        $this->testClient->set($this->subject->getId(), serialize(
            serialize([ 'test-key' => 'test value', ])
        ));

        $this->subject->start();

        $this->assertEquals('test value', $this->subject->get('test-key'));
    }

    /**
     * @group laravel-only
     */
    public function testSavesSessionData()
    {
        $this->subject->start();
        $this->subject->put('test-key', 'test value');
        $this->subject->save();

        $this->assertRedisKeyEquals($this->subject->getId(), serialize(
            serialize($this->subject->all())
        ));
    }
}
