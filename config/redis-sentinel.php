<?php

/*
|------------------------------------------------------------------------------
| Laravel Redis Sentinel Drivers Package Configuration
|------------------------------------------------------------------------------
|
| This file describes the configuration used by the Redis Sentinel services
| provided by this package. The default configuration structure below enables
| simple, environment-based configuration, especially for Lumen applications,
| without the need to create or modify application configuration files.
|
| Each of the "redis-sentinel" keys in this configuration will merge into the
| main application configuration hierarchy at the same path. Please note that
| this merge is not recursive--if the key already exists, the configuration
| in memory takes precedence over this configuration file when the package
| boots. This design allows developers to override the default configuration
| when needed.
|
| For advanced configuration that exceeds the capacity of this configuration
| file, developers may add the "redis-sentinel" configuration blocks to each
| of the application configuration files with the same name as the top-level
| key. Lumen users may copy this configuration file to the "config/" directory
| in the project root directory (which may need to be created) to avoid adding
| several application configuration files just to configure this package.
| Because the package merges these settings into the application's main
| configuration, it does not publish the "redis-sentinel.php" config file
| automatically.
|
| For more configuration information, please see the package's README:
|
|  https://github.com/monospice/laravel-redis-sentinel-drivers
|
*/

$host = env('REDIS_SENTINEL_HOST', env('REDIS_HOST', 'localhost'));
$port = env('REDIS_SENTINEL_PORT', env('REDIS_PORT', 26379));
$password = env('REDIS_SENTINEL_PASSWORD', env('REDIS_PASSWORD', null));
$database = env('REDIS_SENTINEL_DATABASE', env('REDIS_DATABASE', 0));
$service = env('REDIS_SENTINEL_SERVICE', 'mymaster');

return [

    /*
    |--------------------------------------------------------------------------
    | Automatic Configuration Controls
    |--------------------------------------------------------------------------
    |
    | These values enable developers to control how the package loads and
    | manages its configuration.
    |
    | By default, this package attempts to load its configuration from the
    | environment and automatically merge it into the config entries for the
    | corresponding application components to relieve developers of the need
    | to set up config blocks in multiple configuration files. For advanced
    | configuration, such as when an application provides its own config files
    | for the package, developers can disable this behavior by setting the
    | value of "load_config" to FALSE so the package skips auto-configuration.
    |
    | After loading its configuration and merging the values for the other
    | components, the package no longer requires its own configuration, so it
    | removes entries under the "redis-sentinel" top-level config key. This
    | prevents the artisan "config:cache" command from saving the unnecessary
    | values to the cache file. If the application uses this configuration
    | key for other purposes, set the value of "clean_config" to FALSE.
    |
    | The "auto_boot" directive instructs the package to immediately boot its
    | services after the registration phase. This allows applications to use
    | Sentinel-backed services in service providers during the registration
    | phase for compatibility with other packages that may use cache, session
    | queue, and broadcasting features before the application boot phase.
    |
    */

    'load_config' => true,

    'clean_config' => true,

    'auto_boot' => env('REDIS_SENTINEL_AUTO_BOOT', false),

    /*
    |--------------------------------------------------------------------------
    | Redis Sentinel Database Driver
    |--------------------------------------------------------------------------
    |
    | The following block configures the Redis Sentinel connections for the
    | application.
    |
    | Each of the connections below contains one or more host definitions for
    | the Sentinel servers in the quorum. Each host definition is wrapped in
    | an unnamed array that contains the host's IP address or hostname and the
    | port number. To specify multiple Sentinel hosts for a connection, add a
    | sub-array to the connection's array with the address and port for each
    | Sentinel server. Configurations that place multiple Sentinel servers
    | behind one aggregate hostname, such as "sentinels.example.com", should
    | contain only one host definition per connection.
    |
    | The main "redis-sentinel" driver configuration array and each of the
    | connection arrays within may contain an "options" element that provides
    | additional configuration settings for the connections, such as the
    | password for the Redis servers behind Sentinel, if needed. Any options
    | specified for a connection override the options in the global "options"
    | array that defines options for all connections.
    |
    | We can individually configure each of the application service connections
    | ("broadcasting", "cache", "session", and "queue") with environment
    | variables by setting the variables named for each connection. If more
    | than one connection shares a common configuration value, we can instead
    | set the environment variable that applies to all of the Sentinel
    | connections.
    |
    | For example, we may set the following configuration in ".env" for a setup
    | that uses the same Sentinel hosts for the application's cache and queue,
    | but a different Redis database for each connection:
    |
    |     REDIS_HOST=sentinels.example.com
    |     REDIS_CACHE_DATABASE=1
    |     REDIS_QUEUE_DATABASE=2
    |
    | Developers need only supply environment configuration variables for the
    | Sentinel connections used by the application.
    |
    | To simplify environment configuration, this script attempts to read both
    | the "REDIS_SENTINEL_*" and the "REDIS_*" environment variables that
    | specify values shared by multiple Sentinel connections. If an application
    | does not require both Redis Sentinel and standard Redis connections at
    | the same time, this feature allows developers to use the same environment
    | variale names in development (with a single Redis server) and production
    | (with a full set of Sentinel servers). These variables are:
    |
    |     REDIS_SENTINEL_HOST (REDIS_HOST)
    |     REDIS_SENTINEL_PORT (REDIS_PORT)
    |     REDIS_SENTINEL_PASSWORD (REDIS_PASSWORD)
    |     REDIS_SENTINEL_DATABASE (REDIS_DATABASE)
    |
    | The package supports environment-based configuration for connections with
    | multiple hosts by allowing a comma-seperated string of hosts in each of
    | the "*_HOST" environment variables. For example:
    |
    |     REDIS_HOST=sentinel1.example.com, sentinel2.example.com
    |     REDIS_CACHE_HOST=10.0.0.1, 10.0.0.2, 10.0.0.3
    |     REDIS_QUEUE_HOST=tcp://10.0.0.3:26379, tcp://10.0.0.3:26380
    |
    */

    'database' => [

        'redis-sentinel' => [

            'default' => [
                [
                    'host' => $host,
                    'port' => $port,
                ],
            ],

            'broadcasting' => [
                [
                    'host' => env('REDIS_BROADCAST_HOST', $host),
                    'port' => env('REDIS_BROADCAST_PORT', $port),
                ],
                'options' => [
                    'service' => env('REDIS_BROADCAST_SERVICE', $service),
                    'parameters' => [
                        'password' => env('REDIS_BROADCAST_PASSWORD', $password),
                        'database' => env('REDIS_BROADCAST_DATABASE', $database),
                    ],
                ],
            ],

            'cache' => [
                [
                    'host' => env('REDIS_CACHE_HOST', $host),
                    'port' => env('REDIS_CACHE_PORT', $port),
                ],
                'options' => [
                    'service' => env('REDIS_CACHE_SERVICE', $service),
                    'parameters' => [
                        'password' => env('REDIS_CACHE_PASSWORD', $password),
                        'database' => env('REDIS_CACHE_DATABASE', $database),
                    ],
                ],
            ],

            'session' => [
                [
                    'host' => env('REDIS_SESSION_HOST', $host),
                    'port' => env('REDIS_SESSION_PORT', $port),
                ],
                'options' => [
                    'service' => env('REDIS_SESSION_SERVICE', $service),
                    'parameters' => [
                        'password' => env('REDIS_SESSION_PASSWORD', $password),
                        'database' => env('REDIS_SESSION_DATABASE', $database),
                    ],
                ],
            ],

            'queue' => [
                [
                    'host' => env('REDIS_QUEUE_HOST', $host),
                    'port' => env('REDIS_QUEUE_PORT', $port),
                ],
                'options' => [
                    'service' => env('REDIS_QUEUE_SERVICE', $service),
                    'parameters' => [
                        'password' => env('REDIS_QUEUE_PASSWORD', $password),
                        'database' => env('REDIS_QUEUE_DATABASE', $database),
                    ],
                ],
            ],

            // These options apply to all Redis Sentinel connections unless a
            // connection supplies a local options array that overrides the
            // values here:
            'options' => [
                'service' => $service,

                'parameters' => [
                    'password' => $password,
                    'database' => $database,
                ],

                'sentinel_timeout' => env('REDIS_SENTINEL_TIMEOUT', 0.100),
                'retry_limit' => env('REDIS_SENTINEL_RETRY_LIMIT', 20),
                'retry_wait' => env('REDIS_SENTINEL_RETRY_WAIT', 1000),
                'update_sentinels' => env('REDIS_SENTINEL_DISCOVERY', false),
            ],

        ],

        // Set the value of "REDIS_DRIVER" to "redis-sentinel" to override
        // Laravel's standard Redis API ("Redis" facade and "redis" service
        // binding) so that these use the Redis Sentinel connections instead
        // of the Redis connections.
        'redis' => [
            'driver' => env('REDIS_DRIVER', 'default'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Sentinel Broadcasting Connection
    |--------------------------------------------------------------------------
    |
    | Defines the broadcasting connection that uses a Redis Sentinel connection
    | for the application event broadcasting services.
    |
    */

    'broadcasting' => [
        'connections' => [
            'redis-sentinel' => [
                'driver' => 'redis-sentinel',
                'connection' => env(
                    'BROADCAST_REDIS_SENTINEL_CONNECTION',
                    env('BROADCAST_REDIS_CONNECTION', 'broadcasting')
                ),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Sentinel Cache Store
    |--------------------------------------------------------------------------
    |
    | Defines the cache store that uses a Redis Sentinel connection for the
    | application cache.
    |
    */

    'cache' => [
        'stores' => [
            'redis-sentinel' => [
                'driver' => 'redis-sentinel',
                'connection' => env(
                    'CACHE_REDIS_SENTINEL_CONNECTION',
                    env('CACHE_REDIS_CONNECTION', 'cache')
                ),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Sentinel Session Connection
    |--------------------------------------------------------------------------
    |
    | Defines the Redis Sentinel connection used store and retrieve sessions
    | when "SESSION_DRIVER" ("session.driver") is set to "redis-sentinel".
    |
    | The package only uses this value if the application supports sessions
    | (Lumen applications > 5.2 typically don't).
    |
    */

    'session' => [
        'connection' => env('SESSION_CONNECTION', 'session'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Sentinel Queue Connector
    |--------------------------------------------------------------------------
    |
    | Defines the queue connector that uses a Redis Sentinel connection for the
    | application queue.
    |
    */

    'queue' => [
        'connections' => [
            'redis-sentinel' => [
                'driver' => 'redis-sentinel',
                'connection' => env(
                    'QUEUE_REDIS_SENTINEL_CONNECTION',
                    env('QUEUE_REDIS_CONNECTION', 'queue')
                ),
                'queue' => 'default',
                'retry_after' => 90,
                'expire' => 90, // Legacy, Laravel < 5.4.30
            ],
        ],
    ],

];
