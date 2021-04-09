<?php

namespace Monospice\LaravelRedisSentinel\Connectors;

use Illuminate\Support\Arr;
use Monospice\LaravelRedisSentinel\Connections\PredisConnection;
use Illuminate\Redis\Connectors\PhpRedisConnector as LaravelPhpRedisConnector;
use Monospice\LaravelRedisSentinel\Connections\PhpRedisConnection;
use RedisSentinel;

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
    protected $servers = [];

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
        $connector = function () use ($servers, $options) {
            return $this->createClientWithSentinel($servers, $options);
        };

        return new PhpRedisConnection($connector(), $connector, $sentinelOpts);
    }

    /**
     * Create the Redis client instance.
     *
     * @param  array  $servers
     * @param  array  $options
     * @return \Redis
     */
    protected function createClientWithSentinel(array $servers, array $options)
    {
        shuffle($servers);

        foreach ($servers as $server) {
            $sentinel = new RedisSentinel(
                $server['host'] ?? 'localhost',
                $server['port'] ?? 26739,
                $options['sentinel_timeout'] ?? 0,
                $options['sentinel_persistent'] ?? null,
                $options['retry_wait'] ?? 0,
                $options['sentinel_read_timeout'] ?? 0,
            );

            // @TODO update_sentinels
            // $this->servers = $sentinel->sentinels($options['service'] ?? 'mymaster');
            // var_dump($sentinel->sentinels($options['service'] ?? 'mymaster'));
            // var_dump($sentinel->masters());

            $master = $sentinel->getMasterAddrByName($options['service'] ?? 'mymaster');
            if ($master !== false) {
                $config['host'] = $master[0];
                $config['port'] = $master[1];

                var_dump($config);

                return $this->createClient($config);
            }
        }
    }
}
