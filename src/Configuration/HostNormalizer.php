<?php

namespace Monospice\LaravelRedisSentinel\Configuration;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Normalizes host definitions in the package's Redis Sentinel connection
 * configuration by splitting host definitions that contain multiple hosts
 * into individual host definitions to support environment-based configuration.
 *
 * The package allows developers to specify multiple hosts in a single Redis
 * Sentinel "*_HOST" environment variable to provide a way for developers to
 * configure multiple hosts for a connection through the environment without
 * the need to modify the package's default configuration. An environment
 * variable can contain a comma-seperated string of hosts that the package will
 * split automatically using this class:
 *
 *     REDIS_HOST=sentinel1.example.com,sentinel2.example.com
 *
 * Before parsing the connection configuration, the connection config would
 * contain the following value if using the environment variable above:
 *
 *     'connection' => [
 *         [
 *             'host' => 'sentinel1.example.com,sentinel2.example.com',
 *             'port' => 26379,
 *         ]
 *     ]
 *
 * This class will convert the connection configuration to:
 *
 *     'connection' => [
 *         [
 *             'host' => 'sentinel1.example.com',
 *             'port' => 26379,
 *         ],
 *         [
 *             'host' => 'sentinel2.example.com',
 *             'port' => 26379,
 *         ]
 *     ]
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     https://github.com/monospice/laravel-redis-sentinel-drivers
 */
class HostNormalizer
{
    /**
     * Create single host entries for any host definitions that specify
     * multiple hosts in the provided set of connection configurations.
     *
     * @param array $connections The set of connection configs containing host
     * definitions to normalize
     *
     * @return array The normalized Redis Sentinel connection configuration
     */
    public static function normalizeConnections(array $connections)
    {
        foreach ($connections as $name => $connection) {
            if ($name === 'options' || $name === 'clusters') {
                continue;
            }

            $connections[$name] = static::normalizeConnection($connection);
        }

        return $connections;
    }

    /**
     * Create single host entries for any host definitions that specify
     * multiple hosts in the provided connection configuration.
     *
     * @param array $connection The connection config which contains the host
     * definitions for a single Redis Sentinel connection
     *
     * @return array The normalized connection configuration values
     */
    public static function normalizeConnection(array $connection)
    {
        $normal = [ ];

        if (array_key_exists('options', $connection)) {
            $normal['options'] = $connection['options'];
            unset($connection['options']);
        }

        foreach ($connection as $host) {
            $normal = array_merge($normal, static::normalizeHost($host));
        }

        return $normal;
    }

    /**
     * Parse the provided host definition into multiple host definitions if it
     * specifies more than one host.
     *
     * @param array|string $host The host definition from a Redis Sentinel
     * connection
     *
     * @return array One or more host definitions parsed from the provided
     * host definition
     */
    public static function normalizeHost($host)
    {
        if (is_array($host)) {
            return static::normalizeHostArray($host);
        }

        if (is_string($host)) {
            return static::normalizeHostString($host);
        }

        return [ $host ];
    }

    /**
     * Parse a host definition in the form of an array into multiple host
     * definitions if it specifies more than one host.
     *
     * @param array $hostArray The host definition from a Redis Sentinel
     * connection
     *
     * @return array One or more host definitions parsed from the provided
     * host definition
     */
    protected static function normalizeHostArray(array $hostArray)
    {
        if (! array_key_exists('host', $hostArray)) {
            return [ $hostArray ];
        }

        $port = Arr::get($hostArray, 'port', 26379);

        return static::normalizeHostString($hostArray['host'], $port);
    }

    /**
     * Parse a host definition in the form of a string into multiple host
     * definitions it it specifies more than one host.
     *
     * @param string $hostString The host definition from a Redis Sentinel
     * connection
     * @param int    $port       The port number to use for the resulting host
     * definitions if the parsed host definition doesn't contain port numbers
     *
     * @return array One or more host definitions parsed from the provided
     * host definition
     */
    protected static function normalizeHostString($hostString, $port = 26379)
    {
        $hosts = [ ];

        foreach (explode(',', $hostString) as $host) {
            $host = trim($host);

            if (Str::contains($host, ':')) {
                $hosts[] = $host;
            } else {
                $hosts[] = [ 'host' => $host, 'port' => $port ];
            }
        }

        return $hosts;
    }
}
