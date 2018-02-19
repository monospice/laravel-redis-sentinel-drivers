<?php

namespace Monospice\LaravelRedisSentinel\Connectors;

use Illuminate\Support\Arr;
use Monospice\LaravelRedisSentinel\Connections\PredisConnection;
use Predis\Client;

/**
 * Initializes Predis Client instances for Redis Sentinel connections
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     http://github.com/monospice/laravel-redis-sentinel-drivers
 */
class PredisConnector
{
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
    public function connect(array $server, array $options = [ ])
    {
        // Merge the global options shared by all Sentinel connections with
        // connection-specific options
        $clientOpts = array_merge($options, Arr::pull($server, 'options', [ ]));

        // Automatically set "replication" to "sentinel". This is the Sentinel
        // driver, after all.
        $clientOpts['replication'] = 'sentinel';

        // Extract the array of Sentinel connection options from the rest of
        // the client options
        $sentinelOpts = array_intersect_key($clientOpts, $this->sentinelKeys);

        // Filter the Sentinel connection options elements from the client
        // options array
        $clientOpts = array_diff_key($clientOpts, $this->sentinelKeys);

        return new PredisConnection(
            new Client($server, $clientOpts),
            $sentinelOpts
        );
    }
}
