<?php

namespace Monospice\LaravelRedisSentinel\Connectors;

use Illuminate\Support\Arr;
use Illuminate\Redis\Connectors\PhpRedisConnector as LaravelPhpRedisConnector;
use LogicException;
use Monospice\LaravelRedisSentinel\Connections\PhpRedisConnection;
use Monospice\LaravelRedisSentinel\Exceptions\RedisRetryException;
use Redis;
use RedisSentinel;
use RedisException;

/**
 * Initializes PhpRedis Client instances for Redis Sentinel connections
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Jeffrey Zant <j.zant@slash2.nl>
 * @license  See LICENSE file
 * @link     http://github.com/monospice/laravel-redis-sentinel-drivers
 */
class PhpRedisConnector extends LaravelPhpRedisConnector
{
    /**
     * Holds the current sentinel servers.
     *
     * @var array
     */
    protected $servers;

    /**
     * The number of times the client attempts to retry a command when it fails
     * to connect to a Redis instance behind Sentinel.
     *
     * @var int
     */
    protected $connectorRetryLimit = 20;

    /**
     * The time in milliseconds to wait before the client retries a failed
     * command.
     *
     * @var int
     */
    protected $connectorRetryWait = 1000;

    /**
     * Configuration options specific to Sentinel connection operation
     *
     * Some of the Sentinel configuration options can be entered in this class.
     * The retry_wait and retry_limit values are passed to the connection.
     *
     * @var array
     */
    protected $sentinelKeys = [
        'sentinel_timeout' => null,
        'retry_wait' => null,
        'retry_limit' => null,
        'update_sentinels' => null,

        'sentinel_persistent' => null,
        'sentinel_read_timeout' => null,
    ];

    /**
     * Instantiate the connector and check if the required extension is available.
     */
    public function __construct()
    {
        if (! extension_loaded('redis')) {
            throw new LogicException('Please make sure the PHP Redis extension is installed and enabled.');
        }

        if (! class_exists(RedisSentinel::class)) {
            throw new LogicException('Please make sure the PHP Redis extension is up to date (5.3.4 or greater).');
        }
    }

    /**
     * Create a new Redis Sentinel connection from the provided configuration
     * options
     *
     * @param array $server  The client configuration for the connection
     * @param array $options The global client options shared by all Sentinel
     * connections
     *
     * @return PhpRedisConnection The Sentinel connection containing a configured
     * PhpRedis Client
     */
    public function connect(array $servers, array $options = [ ])
    {
        // Set the initial Sentinel servers.
        $this->servers = array_map(function ($server) {
            return $this->formatServer($server);
        }, $servers);

        // Set the connector retry limit.
        if (isset($options['connector_retry_limit']) && is_numeric($options['connector_retry_limit'])) {
            $this->connectorRetryLimit = (int) $options['connector_retry_limit'];
        }

        // Set the connector retry wait.
        if (isset($options['connector_retry_wait']) && is_numeric($options['connector_retry_wait'])) {
            $this->connectorRetryWait = (int) $options['connector_retry_wait'];
        }

        // Merge the global options shared by all Sentinel connections with
        // connection-specific options
        $clientOpts = array_merge($options, Arr::pull($servers, 'options', [ ]));

        // Extract the array of Sentinel connection options from the rest of
        // the client options
        $sentinelOpts = array_intersect_key($clientOpts, $this->sentinelKeys);

        // Filter the Sentinel connection options elements from the client
        // options array
        $clientOpts = array_diff_key($clientOpts, $this->sentinelKeys);

        // Create a client by calling the Sentinel servers
        $connector = function () use ($options) {
            return $this->createClientWithSentinel($options);
        };

        // Create a connection and retry if this fails.
        $connection = self::retryOnFailure(function () use ($connector) {
            return $connector();
        }, $this->connectorRetryLimit, $this->connectorRetryWait);

        return new PhpRedisConnection($connection, $connector, $sentinelOpts);
    }

    /**
     * Create the Redis client instance
     *
     * @param  array  $options
     * @return Redis
     *
     * @throws LogicException
     */
    protected function createClientWithSentinel(array $options)
    {
        $serverConfigurations = $this->servers;
        $clientConfiguration = isset($options['parameters']) ? $options['parameters'] : [];

        $updateSentinels = isset($options['update_sentinels']) ? $options['update_sentinels'] : false;
        $sentinelService = isset($options['service']) ? $options['service'] : 'mymaster';
        $sentinelTimeout = isset($options['sentinel_timeout']) ? $options['sentinel_timeout'] : 0;
        $sentinelPersistent = isset($options['sentinel_persistent']) ? $options['sentinel_persistent'] : null;
        $sentinelReadTimeout = isset($options['sentinel_read_timeout']) ? $options['sentinel_read_timeout'] : 0;

        // Shuffle the server configurations to perform some loadbalancing.
        shuffle($serverConfigurations);

        // Try to connect to any of the servers.
        foreach ($serverConfigurations as $idx => $serverConfiguration) {
            $host = isset($serverConfiguration['host']) ? $serverConfiguration['host'] : 'localhost';
            $port = isset($serverConfiguration['port']) ? $serverConfiguration['port'] : 26379;

            // Create a connection to the Sentinel instance. Using a retry_interval of 0, retrying
            // is done inside the PhpRedisConnection. Cannot seem to get the retry_interval to work:
            // https://github.com/phpredis/phpredis/blob/37a90257d09b4efa75230769cf535484116b2b67/library.c#L343
            $sentinel = new RedisSentinel($host, $port, $sentinelTimeout, $sentinelPersistent, 0, $sentinelReadTimeout);

            try {
                // Check if the Sentinel server list needs to be updated.
                // Put the current server and the other sentinels in the server list.
                if ($updateSentinels === true) {
                    $this->updateSentinels($sentinel, $host, $port, $sentinelService);
                }

                // Lookup the master node.
                $master = $sentinel->getMasterAddrByName($sentinelService);
                if (is_array($master) && ! count($master)) {
                    throw new RedisException(sprintf('No master found for service "%s".', $sentinelService));
                }

                // Create a PhpRedis client for the selected master node.
                return $this->createClient(array_merge($clientConfiguration, $serverConfiguration, [
                    'host' => $master[0],
                    'port' => $master[1],
                ]));
            } catch (RedisException $e) {
                // Rethrow the expection when the last server is reached.
                if ($idx === count($serverConfigurations) - 1) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Update the list With sentinel servers.
     *
     * @param RedisSentinel $sentinel
     * @param string $currentHost
     * @param int $currentPort
     * @param string $service
     * @return void
     */
    protected function updateSentinels(RedisSentinel $sentinel, string $currentHost, int $currentPort, string $service)
    {
        $this->servers = array_merge(
            [
                [
                    'host' => $currentHost,
                    'port' => $currentPort,
                ]
            ], array_map(function ($sentinel) {
                return [
                    'host' => $sentinel['ip'],
                    'port' => $sentinel['port'],
                ];
            }, $sentinel->sentinels($service))
        );
    }

    /**
     * Format a server.
     *
     * @param mixed $server
     * @return array
     *
     * @throws RedisException
     */
    protected function formatServer($server)
    {
        if (is_string($server)) {
            list($host, $port) = explode(':', $server);
            if (! $host || ! $port) {
                throw new RedisException('Could not format the server definition.');
            }

            return ['host' => $host, 'port' => (int) $port];
        }

        if (! is_array($server)) {
            throw new RedisException('Could not format the server definition.');
        }

        return $server;
    }

    /**
     * Retry the callback when a RedisException is catched.
     *
     * @param callable $callback The operation to execute.
     * @param int $retryLimit The number of times the retry is performed.
     * @param int $retryWait The time in milliseconds to wait before retrying again.
     * @param callable $failureCallback The operation to execute when a failure happens.
     * @return mixed The result of the first successful attempt.
     *
     * @throws RedisRetryException After exhausting the allowed number of
     * attempts to connect.
     */
    public static function retryOnFailure(callable $callback, int $retryLimit, int $retryWait, callable $failureCallback = null)
    {
        $attempts = 0;
        $previousException = null;

        while ($attempts < $retryLimit) {
            try {
                return $callback();
            } catch (RedisException $exception) {
                $previousException = $exception;

                if ($failureCallback) {
                    call_user_func($failureCallback);
                }

                usleep($retryWait * 1000);

                $attempts++;
            }
        }

        throw new RedisRetryException(sprintf('Reached the (re)connect limit of %d attempts', $attempts), 0, $previousException);
    }
}
