<?php

namespace Monospice\LaravelRedisSentinel\Tests\Support;

use Closure;
use Monospice\SpicyIdentifiers\DynamicMethod;
use Predis\Client;
use Symfony\Component\Process\PhpProcess;

/**
 * A Predis Client wrapper that connects to the same Sentinel servers as the
 * classes under test for behavior verification and test clean-up.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     https://github.com/monospice/laravel-redis-sentinel-drivers
 */
class TestClient
{
    /**
     * Indicates to the parent process that a background PHP process is ready
     * to execute its Redis commands.
     *
     * @var string
     */
    const BACKGROUND_PROCESS_READY_BEACON = '::READY::';

    /**
     * An instance of the Predis Client that connects to the same Sentinel
     * servers as the classes under test for behavior verification and test
     * clean-up.
     *
     * @var Client
     */
    protected $client;

    /**
     * Initalize a supporting test client used to validate Redis operations and
     * control server availability.
     *
     * @param array $sentinels The Sentinel hosts to test against.
     * @param array $options   Testing-specific connection options.
     *
     * @return void
     */
    public function __construct(array $sentinels, array $options)
    {
        $this->client = new Client($sentinels, array_merge($options, [
            'parameters' => array_merge($options['parameters'], [
                'persistent' => true,
            ]),
        ]));

        $connection = $this->client->getConnection();
        $connection->setRetryWait($options['parameters']['timeout']);
        $connection->setRetryLimit(3);
    }

    /**
     * Signal the current Redis master to sleep for the specified number of
     * seconds.
     *
     * WARNING: Performing this operation with a duration greater than the
     * value of the Sentinel down-after-milliseconds directive for the current
     * master group will cause Sentinel to initiate a failover.
     *
     * @param int     $seconds  The number of seconds the master will sleep for.
     * @param Closure $callback Executes any operations to perform after putting
     * the master to sleep. If NULL, the call blocks until the master wakes up.
     *
     * @return void
     */
    public function blockMasterFor($seconds, Closure $callback = null)
    {
        if ($callback === null) {
            $this->getMaster()->executeRaw([ 'DEBUG', 'SLEEP', $seconds ]);

            return;
        }

        $process = $this->makeBackgroundCommandProcessForMaster("
            \$client->executeRaw([ 'DEBUG', 'SLEEP', $seconds ]);
        ");

        $process->mustRun(function ($type, $buffer) use ($callback) {
            if ($buffer === self::BACKGROUND_PROCESS_READY_BEACON) {
                $callback();
            }
        });
    }

    /**
     * Get a client instance for the current Redis master.
     *
     * @return \Predis\ClientInterface Connects to the current master without
     * querying Sentinel.
     */
    public function getMaster()
    {
        return $this->client->getClientFor('master');
    }

    /**
     * Proxy dynamic method calls to the current Predis client instance.
     *
     * @param string $method    The name of the invoked method.
     * @param array  $arguments Any arguments passed to the method.
     *
     * @return mixed The return value from the proxied method call.
     */
    public function __call($method, array $arguments)
    {
        return DynamicMethod::from($method)->callOn($this->client, $arguments);
    }

    /**
     * Initialize an object that starts a background PHP process to execute
     * the provided Redis command(s) without blocking the test.
     *
     * @param string $script The Redis commands to execute represented as a PHP
     * script using the Predis client.
     *
     * @return PhpProcess Used to start the background process and collect any
     * output.
     */
    protected function makeBackgroundCommandProcessForMaster($script)
    {
        $parameters = $this->getMaster()->getConnection()->getParameters();
        $scriptPath = __DIR__;

        return new PhpProcess("
            <?php
            require_once '$scriptPath/../../vendor/autoload.php';

            \$client = new Predis\Client([
                'scheme' => '{$parameters->scheme}',
                'host' => '{$parameters->host}',
                'port' => {$parameters->port},
                'database' => {$parameters->database},
            ]);

            \$client->ping();

            echo '" . self::BACKGROUND_PROCESS_READY_BEACON . "';
            flush();

            $script
            ?>
        ");
    }
}
