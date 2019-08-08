<?php

namespace Monospice\LaravelRedisSentinel\Tests\Integration\Horizon;

use Illuminate\Contracts\Console\Kernel as Artisan;
use Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider;
use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
use Monospice\LaravelRedisSentinel\Tests\Support\ArtisanProcess;
use Monospice\LaravelRedisSentinel\Tests\Support\Doubles\JobStub;
use Monospice\LaravelRedisSentinel\Tests\Support\IntegrationTestCase;
use RuntimeException;

class HorizonCommandTest extends IntegrationTestCase
{
    /**
     * The key of the default ready queue.
     *
     * @var string
     */
    const QUEUE = 'queues:default';

    /**
     * Used to execute Horizon Artisan commands.
     *
     * @var Artisan
     */
    protected $artisan;

    /**
     * Enqueues jobs for testing.
     *
     * @var \Illuminate\Contracts\Queue\Queue
     */
    protected $queue;

    /**
     * Indicates whether we started a Horizon supervisor that we need to
     * terminate at the end of a test.
     *
     * @var bool
     */
    protected $supervisorStarted;

    /**
     * Run this setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $app = ApplicationFactory::makeForConsole();
        $app->config->set(require(__DIR__ . '/../../stubs/config.php'));
        $app->config->set('cache.default', 'redis-sentinel'); // "queue:work"
        $app->config->set('database.redis-sentinel', $this->config);

        ApplicationFactory::configureHorizonComponents($app);

        $app->register(RedisSentinelServiceProvider::class);
        $app->boot();

        $this->artisan = $app->make(Artisan::class);
        $this->queue = $app->queue->connection('redis-sentinel');

        $this->artisan->bootstrap();
    }

    /**
     * Run this cleanup after each test.
     *
     * @return void
     */
    public function tearDown()
    {
        if ($this->supervisorStarted) {
            $this->artisan->call('horizon:terminate');
            $this->artisan->call('horizon:purge');

            $this->supervisorStarted = false;
        }

        parent::tearDown();
    }

    /**
     * @group horizon
     */
    public function testCanControlHorizonSupervisorLifecycle()
    {
        $process = $this->startMasterSupervisorProcess();

        $this->assertTrue($process->isRunning());
        $this->assertRedisSortedSetCount('horizon:masters', 1);

        $this->assertEquals(0, $this->artisan->call('horizon:pause'));
        $this->assertEquals(0, $this->artisan->call('horizon:continue'));
        $this->assertEquals(0, $this->artisan->call('horizon:terminate'));

        usleep(1.5 * 1000000);

        $this->assertRedisSortedSetCount('horizon:masters', 0);
    }

    /**
     * @group horizon
     */
    public function testProcessesQueuedJobsManually()
    {
        $jobCount = 3;

        for ($jobNumber = 1; $jobNumber <= $jobCount; $jobNumber++) {
            $this->queue->push(new JobStub());

            $this->assertRedisKeyExists("horizon:$jobNumber");
        }

        $this->assertRedisListCount(self::QUEUE, $jobCount);

        for ($jobNumber = $jobCount; $jobNumber > 0; $jobNumber--) {
            $exitCode = $this->artisan->call('horizon:work', [
                'connection' => 'redis-sentinel',
                '--once' => true
            ]);
            $output = $this->artisan->output();

            $this->assertEquals(0, $exitCode);
            $this->assertRegExp('/Processed:.*JobStub/', $output);
        }

        $this->assertRedisListCount(self::QUEUE, 0);
    }

    /**
     * @group horizon
     */
    public function testProcessesQueuedJobsViaSupervisor()
    {
        $jobCount = 3;

        for ($jobNumber = 1; $jobNumber <= $jobCount; $jobNumber++) {
            $this->queue->push(new JobStub());

            $this->assertRedisKeyExists("horizon:$jobNumber");
        }

        $this->assertRedisListCount(self::QUEUE, $jobCount);

        $process = $this->startMasterSupervisorProcess();

        // Wait for the workers to spin up and process the queued jobs:
        while ($process->isRunning()) {
            if ($this->testClient->llen(self::QUEUE) === 0) {
                break;
            }

            $this->checkTimeout($process, 'Supervisor did not process queue.');
            usleep(10000);
        }

        $this->assertRedisListCount(self::QUEUE, 0);
    }

    /**
     * @group horizon
     */
    public function testCollectsAMetricsSnapshot()
    {
        $this->assertEquals(0, $this->artisan->call('horizon:snapshot'));
        $this->assertRedisKeyExists('horizon:metrics:snapshot');
    }

    /**
     * @group horizon
     */
    public function testInformationalCommandsSucceed()
    {
        $this->assertEquals(0, $this->artisan->call('horizon:list'));
        $this->assertEquals(0, $this->artisan->call('horizon:supervisors'));
    }

    /**
     * Start a background process for the Horizon master supervisor.
     *
     * @return ArtisanProcess The running process object if it succeeds.
     */
    protected function startMasterSupervisorProcess()
    {
        $process = new ArtisanProcess($this->config, 'horizon');
        $process->setIdleTimeout(10);
        $process->start();

        $this->supervisorStarted = true;
        $failureMessage = 'Could not run master supervisor process.';

        while ($process->isRunning()) {
            usleep(2000);

            if (strpos($process->getOutput(), 'Horizon started') !== false) {
                usleep(10000);

                return $process;
            }

            $this->checkTimeout($process, $failureMessage);
        }

        $this->checkTimeout($process, $failureMessage);
    }

    /**
     * Handle a background process timeout by throwing and exception and
     * showing any process output.
     *
     * @param ArtisanProcess $process The background process to check.
     * @param string         $message A failure message to show if timed out.
     *
     * @return void
     *
     * @throws RuntimeException When the specified process exceeded its allowed
     * runtime.
     */
    protected function checkTimeout(ArtisanProcess $process, $message = null)
    {
        try {
            if (! $process->isRunning()) {
                throw new RuntimeException('Process terminated unexpectedly.');
            }

            $process->checkTimeout();
        } catch (RuntimeException $exception) {
            throw new RuntimeException(
                $message . PHP_EOL
                . 'Output: ' . $process->getOutput() . PHP_EOL
                . 'Error Output: ' . $process->getErrorOutput() . PHP_EOL,
                $exception->getCode(),
                $exception
            );
        }
    }
}
