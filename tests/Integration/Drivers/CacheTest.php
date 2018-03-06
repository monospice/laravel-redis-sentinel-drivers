<?php

namespace Monospice\LaravelRedisSentinel\Tests\Integration\Drivers;

use Illuminate\Cache\RedisStore;
use Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider;
use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
use Monospice\LaravelRedisSentinel\Tests\Support\IntegrationTestCase;

class CacheTest extends IntegrationTestCase
{
    /**
     * The instance of the Redis Sentinel cache store under test.
     *
     * @var Illuminate\Contracts\Cache\Repository
     */
    protected $subject;

    /**
     * The prefix prepended to cache keys stored in Redis.
     *
     * @var string
     */
    protected $cachePrefix;

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

        if (! ApplicationFactory::isLumen()) {
            $app->boot();
        }

        $this->cachePrefix = 'test';
        $app->config->set('cache.prefix', $this->cachePrefix);

        $this->subject = $app->cache->store('redis-sentinel');
    }

    public function testIsBackedByARedisCacheStore()
    {
        $this->assertInstanceOf(RedisStore::class, $this->subject->getStore());
    }

    public function testFetchesCachedData()
    {
        $cacheKey = $this->prefix('test-key');
        $expected = 'test value';

        $this->testClient->set($cacheKey, serialize($expected));

        $this->assertEquals($expected, $this->subject->get('test-key'));
    }

    public function testStoresDataInCache()
    {
        $cacheKey = $this->prefix('test-key');
        $expected = 'test value';

        $this->subject->forever('test-key', $expected);

        $this->assertRedisKeyEquals($cacheKey, serialize($expected));
    }

    public function testStoresPerishableDataInCache()
    {
        $cacheKey = $this->prefix('test-key');
        $expected = 'test value';
        $oneSecondInMinutes = 1 / 60;

        $this->subject->put('test-key', $expected, $oneSecondInMinutes);

        $this->assertRedisKeyEquals($cacheKey, serialize($expected));
        usleep(1.2 * 1000000);
        $this->assertRedisKeyEquals($cacheKey, null);
    }

    /**
     * Prepend the provided key with the current cache prefix.
     *
     * @param string $key The value to prefix
     *
     * @return string The cache key as stored in Redis.
     */
    protected function prefix($key)
    {
        return $this->cachePrefix . ':' . $key;
    }
}
