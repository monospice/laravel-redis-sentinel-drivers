<?php

namespace Monospice\LaravelRedisSentinel\Connectors;

use Illuminate\Support\Arr;
use Monospice\LaravelRedisSentinel\Connections\PredisConnection;
use Illuminate\Redis\Connectors\PhpRedisConnector as LaravelPhpRedisConnector;
use Monospice\LaravelRedisSentinel\Connections\PhpRedisConnection;
use RedisSentinel;
use RedisException;

/**
 * Initializes PhpRedis Client instances for Redis Sentinel connections
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
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
     * We cannot pass these options as an array to the Predis client.
     * Instead, we'll set them on the connection directly using methods
     * provided by the SentinelReplication class of the Predis package.
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
     * @return PredisConnection The Sentinel connection containing a configured
     * Predis Client
     */
    public function connect(array $servers, array $options = [ ])
    {
        // Set the initial Sentinel servers.
        $this->servers = $servers;

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
     * @param  array  $servers
     * @param  array  $options
     * @return \Redis
     */
    protected function createClientWithSentinel(array $options)
    {
        $servers = $this->servers;

        shuffle($servers);

        foreach ($servers as $idx => $server) {
            $host = $server['host'] ?? 'localhost';
            $port = $server['port'] ?? 26739;
            $service = $options['service'] ?? 'mymaster';

            $sentinel = new RedisSentinel(
                $host,
                $port,
                $options['sentinel_timeout'] ?? 0,
                $options['sentinel_persistent'] ?? null,
                $options['retry_wait'] ?? 0,
                $options['sentinel_read_timeout'] ?? 0,
            );

            try {
                if (($options['update_sentinels'] ?? false) === true) {
                    $this->servers = array_merge(
                        [
                            [
                                'host' => $host,
                                'port' => $port,
                            ]
                        ], array_map(fn ($sentinel) => [
                            'host' => $sentinel['ip'],
                            'port' => $sentinel['port'],
                        ], $sentinel->sentinels($service))
                    );
                }

                $master = $sentinel->getMasterAddrByName($service);
                if (is_array($master) && count($master)) {
                    $config['host'] = $master[0];
                    $config['port'] = $master[1];

                    // @TODO rewrite this config for auth.

                    return $this->createClient($config);
                }
            } catch (RedisException $e) {
                // Only throw the exception if the last server can't connect
                if ($idx === count($servers) - 1) {
                    throw $e;
                }
            }
        }
    }
}
