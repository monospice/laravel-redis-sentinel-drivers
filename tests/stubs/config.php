<?php

/**
 * A model configuration for the classes in the package. Used to test against
 * for unit tests.
 */

return [

    // Represents a subset of config/database.php
    'database' => [
        'redis' => [
            'driver' => 'redis-sentinel',
        ],
        'redis-sentinel' => [
            'connection1' => [
                [
                    'host' => 'localhost',
                    'port' => 26379,
                ],
            ],
            'connection2' => [
                [
                    'host' => 'localhost',
                    'port' => 26379,
                ],
                'options' => [
                    'service' => 'another-master',
                ],
            ],
            'options' => [
                'service' => 'mymaster',

                'parameters' => [
                    'password' => 'secret',
                    'database' => 0,
                ],

                'sentinel_timeout' => 0.99, // Predis default: 0.100
                'retry_limit' => 99,        // Predis default: 20
                'retry_wait' => 9999,       // Predis default: 1000
                'update_sentinels' => true, // Predis default: false
            ],
            // This package does not support Redis Cluster connections
            // available in Laravel 5.4+
            'clusters' => [
                'clustered_connection' => [
                ],
            ]
        ],
    ],

    // Represents a subset of config/cache.php
    'cache' => [
        'stores' => [
            'redis-sentinel' => [
                'driver' => 'redis-sentinel',
                'connection' => 'connection1',
            ],
        ],
    ],

    // Represents a subset of config/queue.php
    'queue' => [
        'connections' => [
            'redis-sentinel' => [
                'driver' => 'redis-sentinel',
                'connection' => 'connection1',
                'queue' => 'default',
                'retry_after' => 90,
                'expire' => 90, // Legacy, Laravel < 5.4.30
            ],
        ],
    ],

    // Represents a subset of config/session.php
    'session' => [
        'driver' => 'redis-sentinel',
        'connection' => 'connection1',
    ],

];
