<?php

namespace Monospice\LaravelRedisSentinel\Tests\Integration\Drivers;

use Illuminate\Queue\Jobs\RedisJob;
use Illuminate\Queue\RedisQueue;
use Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider;
use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
use Monospice\LaravelRedisSentinel\Tests\Support\IntegrationTestCase;

class QueueTest extends IntegrationTestCase
{
    /**
     * The key of the default ready queue.
     *
     * @var string
     */
    const QUEUE = 'queues:default';

    /**
     * The key of the default delayed queue.
     *
     * @var string
     */
    const QUEUE_DELAYED = self::QUEUE . ':delayed';

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
        $app->config->set('horizon.driver', 'default');
        $app->register(RedisSentinelServiceProvider::class);

        if (ApplicationFactory::supportsBoot()) {
            $app->boot();
        }

        $this->subject = $app->queue->connection('redis-sentinel');
    }

    public function testIsARedisQueue()
    {
        $this->assertInstanceOf(RedisQueue::class, $this->subject);
    }

    public function testPushesJobOntoQueue()
    {
        $this->assertRedisListCount(self::QUEUE, 0);

        $this->subject->push('TestJob');

        $this->assertRedisListCount(self::QUEUE, 1);
    }

    public function testPopsJobOffQueue()
    {
        $this->subject->push('TestJob');
        $this->assertRedisListCount(self::QUEUE, 1);

        $job = $this->subject->pop();

        $this->assertRedisListCount(self::QUEUE, 0);
        $this->assertInstanceOf(RedisJob::class, $job);
    }

    public function testMovesDelayedJobsToReady()
    {
        $this->subject->later(0, 'TestJob');

        $this->assertRedisListCount(self::QUEUE, 0);
        $this->assertRedisSortedSetCount(self::QUEUE_DELAYED, 1);

        $this->subject->migrateExpiredJobs(self::QUEUE_DELAYED, self::QUEUE);

        $this->assertRedisSortedSetCount(self::QUEUE_DELAYED, 0);
        $this->assertRedisListCount(self::QUEUE, 1);
    }
}
