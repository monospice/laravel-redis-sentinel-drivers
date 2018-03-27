<?php

namespace Monospice\LaravelRedisSentinel;

use Closure;
use Illuminate\Redis\Database as RedisDatabase;
use Illuminate\Support\Arr;
use Monospice\LaravelRedisSentinel\PredisConnection;
use Monospice\SpicyIdentifiers\DynamicMethod;

/**
 * Enables Laravel's Redis database driver to accept configuration options for
 * Redis Sentinel connections independently.
 *
 * By default, Laravel's Redis service permits a single set of configuration
 * options for all of the Redis connections passed to the Predis client. This
 * prevents us from declaring separate parameters for individual Redis services
 * managed by Sentinel. For example, we may wish to connect to a separate Redis
 * Sentinel replication group, or use separate Redis databases for caching,
 * queues, and sessions. This wrapper class enables us to declare parameters
 * for each connection in the "redis-sentinel" block of the database
 * configuration which it will use to configure individual clients.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     https://github.com/monospice/laravel-redis-sentinel-drivers
 */
class RedisSentinelDatabase extends RedisDatabase
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
     * Subscribe to a set of given channels for messages.
     *
     * @param array|string $channels   The names of the channels to subscribe to
     * @param Closure      $callback   Executed for each message. Receives the
     * message string in the first argument and the message channel as the
     * second argument. Return FALSE to unsubscribe.
     * @param string|null  $connection Name of the connection to subscribe with.
     * @param string       $method     The subscription command ("subscribe" or
     * "psubscribe").
     *
     * @return void
     */
    public function subscribe(
        $channels,
        Closure $callback,
        $connection = null,
        $method = 'subscribe'
    ) {
        $this->connection($connection)
            ->createSubscription($channels, $callback, $method);
    }

    /**
     * Subscribe to a set of given channels with wildcards.
     *
     * @param array|string $channels   The names of the channels to subscribe to
     * @param Closure      $callback   Executed for each message. Receives the
     * message string in the first argument and the message channel as the
     * second argument. Return FALSE to unsubscribe.
     * @param string|null  $connection Name of the connection to subscribe with.
     *
     * @return void
     */
    public function psubscribe($channels, Closure $callback, $connection = null)
    {
        $this->subscribe($channels, $callback, $connection, __FUNCTION__);
    }

    /**
     * Create an array of single connection clients.
     *
     * @param array $servers The set of options for each Sentinel connection
     * @param array $options The global options shared by all Sentinel clients
     *
     * @return array Each element contains a Predis client that represents a
     * connection defined in the 'redis-sentinel' block in config/database.php
     */
    protected function createSingleClients(array $servers, array $options = [])
    {
        $clients = [];

        // Laravel < 5.1 doesn't extract the global options from the connection
        // configuration for us
        if (array_key_exists('options', $servers)) {
            $options = (array) Arr::pull($servers, 'options');
        }

        // Automatically set "replication" to "sentinel". This is the Sentinel
        // driver, after all.
        $options['replication'] = 'sentinel';

        foreach ($servers as $key => $server) {
            $clients[$key] = $this->createSingleClient($server, $options);
        }

        return $clients;
    }

    /**
     * Create a Predis client instance for a Redis Sentinel connection
     *
     * @param array $server  The configuration options for the connection
     * @param array $options The global options shared by all Sentinel clients
     *
     * @return Client The Predis Client instance
     */
    protected function createSingleClient(array $server, array $options)
    {
        // Merge the global options shared by all Sentinel connections with
        // connection-specific options
        $clientOpts = (array) Arr::pull($server, 'options');
        $clientOpts = array_merge($options, $clientOpts);

        $sentinelKeys = array_flip($this->sentinelConnectionOptionKeys);

        // Extract the array of Sentinel connection options from the rest of
        // the client options
        $sentinelOpts = array_intersect_key($clientOpts, $sentinelKeys);

        // Filter the Sentinel connection options elements from the client
        // options array
        $clientOpts = array_diff_key($clientOpts, $sentinelKeys);

        $client = new PredisConnection($server, $clientOpts);
        $this->setSentinelConnectionOptions($client, $sentinelOpts);

        return $client;
    }

    /**
     * Sets the Sentinel-specific connection options on a Predis Client
     * connection
     *
     * @param Client $client       The Predis Client to set options for
     * @param array  $sentinelOpts The options supported by Predis for
     * Sentinel-specific connections
     *
     * @return void
     */
    protected function setSentinelConnectionOptions(
        PredisConnection $client,
        array $sentinelOpts
    ) {
        foreach ($sentinelOpts as $option => $value) {
            DynamicMethod::parseFromUnderscore($option)
                ->prepend('set')
                ->callOn($client, [ $value ]);
        }
    }
}
