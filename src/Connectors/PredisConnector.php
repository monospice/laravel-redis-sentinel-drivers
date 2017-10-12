<?php

namespace Monospice\LaravelRedisSentinel\Connectors;

use Illuminate\Support\Arr;
use Monospice\LaravelRedisSentinel\Connections\PredisSentinelConnection;
use Monospice\SpicyIdentifiers\DynamicMethod;
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
    protected $sentinelConnectionOptionKeys = [
        'sentinel_timeout',
        'retry_wait',
        'retry_limit',
        'update_sentinels',
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
        $sentinelKeys = array_flip($this->sentinelConnectionOptionKeys);
        $sentinelOpts = array_intersect_key($clientOpts, $sentinelKeys);

        // Filter the Sentinel connection options elements from the client
        // options array
        $clientOpts = array_diff_key($clientOpts, $sentinelKeys);

        return new PredisSentinelConnection(
            $this->makePredisClient($server, $clientOpts, $sentinelOpts)
        );
    }

    /**
     * Create a Predis Client instance configured with the provided options
     *
     * @param array $server       The client configuration for the connection
     * @param array $clientOpts   Non-sentinel client options
     * @param array $sentinelOpts Sentinel-specific options
     *
     * @return Client The Predis Client configured for Sentinel connections
     */
    protected function makePredisClient(
        array $server,
        array $clientOpts,
        array $sentinelOpts
    ) {
        $client = new Client($server, $clientOpts);
        $connection = $client->getConnection();

        // Set the Sentinel-specific connection options on the Predis Client
        // connection
        foreach ($sentinelOpts as $option => $value) {
            DynamicMethod::parseFromUnderscore($option)
                ->prepend('set')
                ->callOn($connection, [ $value ]);
        }

        return $client;
    }
}
