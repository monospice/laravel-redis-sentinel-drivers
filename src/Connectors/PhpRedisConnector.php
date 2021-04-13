<?php

namespace Monospice\LaravelRedisSentinel\Connectors;

use Illuminate\Support\Arr;
use Illuminate\Redis\Connectors\PhpRedisConnector as LaravelPhpRedisConnector;
use LogicException;
use Monospice\LaravelRedisSentinel\Connections\PhpRedisConnection;
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

        return new PhpRedisConnection($connector(), $connector, $sentinelOpts);
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
        $servers = $this->servers;
        $service = isset($options['service']) ? $options['service'] : 'mymaster';
        $timeout = isset($options['sentinel_timeout']) ? $options['sentinel_timeout'] : 0;
        $persistent = isset($options['sentinel_peristent']) ? $options['sentinel_peristent'] : null;
        $retryWait = isset($options['retry_wait']) ? $options['retry_wait'] : 0;
        $readTimeout = isset($options['sentinel_read_timeout']) ? $options['sentinel_read_timeout'] : 0;
        $parameters = isset($options['parameters']) ? $options['parameters'] : [];

        // Shuffle the servers to perform some loadbalancing.
        shuffle($servers);

        // Check if the redis extension is enabled.
        if (! extension_loaded('redis')) {
            throw new LogicException('Please make sure the PHP Redis extension is installed and enabled.');
        }

        // Check if the extension is up to date and contains RedisSentinel.
        if (! class_exists(RedisSentinel::class)) {
            throw new LogicException('Please make sure the PHP Redis extension is up to date.');
        }

        // Try to connect to any of the servers.
        foreach ($servers as $idx => $server) {
            $host = isset($server['host']) ? $server['host'] : 'localhost';
            $port = isset($server['port']) ? $server['port'] : 26739;

            // Create a connection to the Sentinel instance.
            $sentinel = new RedisSentinel($host, $port, $timeout, $persistent, $retryWait, $readTimeout);

            try {
                // Check if the Sentinel server list needs to be updated.
                // Put the current server and the other sentinels in the server list.
                $updateSentinels = isset($options['update_sentinels']) ? $options['update_sentinels'] : false;
                if ($updateSentinels === true) {
                    $this->updateSentinels($sentinel, $host, $port, $service);
                }

                // Lookup the master node.
                $master = $sentinel->getMasterAddrByName($service);
                if (is_array($master) && ! count($master)) {
                    throw new RedisException(sprintf('No master found for service "%s".', $service));
                }

                // Create a PhpRedis client for the selected master node.
                return $this->createClient(
                    array_merge($parameters, $server, ['host' => $master[0], 'port' => $master[1]])
                );
            } catch (RedisException $e) {
                // Rethrow the expection when the last server is reached.
                if ($idx === count($servers) - 1) {
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
    private function updateSentinels(RedisSentinel $sentinel, string $currentHost, int $currentPort, string $service)
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
    private function formatServer($server)
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
}
