Laravel Drivers for Redis Sentinel
==================================

[![Build Status](https://travis-ci.org/monospice/laravel-redis-sentinel-drivers.svg?branch=master)](https://travis-ci.org/monospice/laravel-redis-sentinel-drivers)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/monospice/laravel-redis-sentinel-drivers/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/monospice/laravel-redis-sentinel-drivers/?branch=master)

**Laravel configuration wrapper for highly-available Redis Sentinel
replication.**

[Redis Sentinel][sentinel] provides high-availability, monitoring, and
load-balancing for Redis servers configured for master-slave replication.
[Laravel][laravel] includes built-in support for Redis, but cannot configure
Sentinel setups flexibly out-of-the-box. This limits configuration of Sentinel
to a single service.

For example, if we wish to use Redis behind Sentinel for both caching and
session handling through Laravel's API, we cannot use separate Redis databases
for cache and session entries like we can in a standard single-server Redis
setup without Sentinel. This causes issues when we need to clear the cache,
because Laravel erases stored session information as well.

This package wraps the configuration of Laravel's Redis caching, session, and
queue APIs for Sentinel with the ability to set options for our Redis services
independently. The package configuration exists separately from Laravel's
default `redis` configuration so we can choose to use the Sentinel connection
as needed by the environment. A developer may use a standalone Redis server in
their local environment, while production environments operate a Redis Sentinel
set of servers.

Requirements
------------

- PHP 5.4 or greater
- [Redis][redis] 2.8 or greater (for Sentinel support)
- [Predis][predis] 1.1 or greater (for Sentinel client support)
- [Laravel][laravel] 5.0 or greater (Laravel 4.x doesn't support the required
  Predis version)

This Readme assumes prior knowledge of configuring [Redis][redis] for [Redis
Sentinel][sentinel] and [using Redis with Laravel][laravel-redis-docs].

Installation
------------

We're using Laravel, so we'll install through composer, of course!

```
composer require monospice/laravel-redis-sentinel-driver
```

If you're not already using Redis with Laravel, this will install the
[Predis][predis] package as well.

To use the drivers, add the package's service provider to `config/app.php`:

```php
...
Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider::class,
...
```

Usage
-----

For the most part, we won't need to interact with the classes in this package
directly. This package is implemented through Laravel configuration files.

- [Configuring the Redis Sentinel Database
  Connection](#database-connection-configuration)
- [Configuring Cache, Session, and Queue
  drivers](#cache-session-and-queue-drivers)
- [Using Sentinel Connections for Standalone Redis
  Commands](#using-sentinel-connections-for-standalone-redis-commands)
- [Connecting to Sentinel Directly](#connecting-to-sentinel-directly)


Database Connection Configuration
---------------------------------

We'll configure the Redis Sentinel database connections separately from
Laravel's default Redis database connection. This enables us to use the
standard Redis functionality side-by-side if needed, such as if a developer
uses a single Redis server in their local environment, while the production
environment operates a full set of Redis and Sentinel servers. We don't need to
remove the `'redis'` driver config block that ships with Laravel by default.

**Note:** Laravel passes these configuration options to the [Predis][predis] client
library, so we can include advanced configuration options here if needed. See
the [Predis Documentation][predis-docs] for more information.

### Basic Configuration

For a simple setup with a single Sentinel server, add the following block to
`config/database.php` for the `'redis-sentinel'` database driver.

```php
...
'redis-sentinel' => [

    'default' => [
        [
            'host' => env('REDIS_SENTINEL_HOST', 'localhost'),
            'port' => env('REDIS_SENTINEL_PORT', 26379),
        ],
    ],

    'options' => [
        'service' => env('REDIS_SENTINEL_SERVICE', 'mymaster'),
        'parameters' => [
            'password' => env('REDIS_PASSWORD', null),
            'database' => 0,
        ],
    ],

],
...
```

As you can see, our `'default'` connection includes the address or hostname of
the Sentinel server and the port that the application connects to (typically
26379). Because Sentinel doesn't support authentication directly, we'll set the
password for our Redis server in the `'parameters'` array, which also includes
the Redis database to use for the connection. The `'service'` option declares
the service name of the set of Redis servers configured in Sentinel as a
service.

Take note of the sub-array in the top level array for the `'default'`
connection. If we choose to add additional Sentinel servers to this
configuration, we'll wrap the definition of each host in another array, like we
can see in the following section.

Of course, be sure to add the environment configuration variables from the
example above to `.env`.

The configuration block above is actually almost a drop-in replacement for
Laravel's built-in `'redis'` connection configuration to use Sentinel without
this package. However, Laravel's Redis configuration offers limited flexibility
for anything more complex than this basic Sentinel setup. A single Sentinel
server or a single connection is typically insufficient for highly-available or
complex applications. We'll take a look at more advanced configuration below.

### Multi-Sentinel Configuration

In a true highly-available Redis setup, we'll run more than one Sentinel server
in a quorum. This adds redundancy for a failure event during which one or more
Sentinel servers become unresponsive. We can add multiple Sentinel server
definitions to our `'default'` connection from the example above:

```php
...
'redis-sentinel' => [

    'default' => [
        [
            'host' => sentinel1.example.com
            'port' => 26379
        ],
        [
            'host' => sentinel2.example.com
            'port' => 26379
        ],
        [
            'host' => sentinel3.example.com
            'port' => 26379
        ],
    ],

    'options' => [
        'service' => env('REDIS_SENTINEL_SERVICE', 'mymaster'),
        'parameters' => [
            'password' => env('REDIS_PASSWORD', null),
            'database' => 0,
        ],
    ],

],
...
```

With this configuration, we declare three Sentinel servers that can handle
requests for our Redis service, `mymaster`. If one of the Sentinel servers
fails, the Predis client will select a different Sentinel server to send
requests to.

Typically, in a clustered environment, we don't want to hard-code each server
into our configuration like above. We may use some form of load balancing or
service discovery to route requests to a Sentinel server through an aggregate
hostname like `sentinels.example.com`, for example, for flexible deployment and
arbritrary scaling. This discussion is outside the scope of this document.

### Multi-service Configuration

As we mentioned previously, we likely want to separate the Redis connections
Laravel uses for each of our services. For example, we'd use separate databases
on a Redis server for our cache and session storage. In this example, we may
also want to create a database on a different set of Redis servers managed by
Sentinel for something like a feed. For this setup, we'll configure multiple
`'redis-sentinel'` connections:

```php
...
'redis-sentinel' => [

    'cache' => [
        [
            'host' => env('REDIS_SENTINEL_HOST', 'localhost'),
            'port' => env('REDIS_SENTINEL_PORT', 26379),
        ],
        'options' => [
            'service' => env('REDIS_SENTINEL_SERVICE', 'mymaster'),
            'parameters' => [
                'password' => env('REDIS_PASSWORD', null),
                'database' => 0,
            ],
        ],
    ],

    'session' => [
        [
            'host' => env('REDIS_SENTINEL_HOST', 'localhost'),
            'port' => env('REDIS_SENTINEL_PORT', 26379),
        ],
        'options' => [
            'service' => env('REDIS_SENTINEL_SERVICE', 'mymaster'),
            'parameters' => [
                'password' => env('REDIS_PASSWORD', null),
                'database' => 1,
            ],
        ],
    ],

    'feed' => [
        [
            'host' => env('REDIS_SENTINEL_HOST', 'localhost'),
            'port' => env('REDIS_SENTINEL_PORT', 26379),
        ],
        'options' => [
            'service' => env('REDIS_SENTINEL_FEED_SERVICE', 'feed-service'),
            'parameters' => [
                'password' => env('REDIS_PASSWORD', null),
                'database' => 0,
            ],
        ],
    ],

],
...
```

Notice that we removed the global `'options'` array and created a local
`'options'` array for each connection. In this example setup, we store
the application cache and sessions on one Redis server set, and feed data
in another set. On the first set, we set the Redis database for storing cache
data to database `0`, and the database for session data to `1`. This enables
us to clear our application cache without erasing user sessions.

Our example setup includes a second set of Redis servers for storing feed data.
The example Sentinel servers contain configuration for the first set with the
service name, `mymaster`, and for the secord set with the service name,
`feed-data`.  The local connection options allow us to specify which service
the connection makes requests for. As you can see, we set the service name of
the `'feed'` connection to `'feed-service'`.

For more information about Sentinel service configuration, see the [Redis
Sentinel Documentation][sentinel].

### Other Sentinel Connection Options

The Predis client supports some additional configuration options that determine
how it handles connections to Sentinel servers. We can add these to the global
`'options'` array for all Sentinel connections or to a local `'options'` array
for a single connection. The default values are shown below:

```php
...
'options' => [
    ...

    // The default amount of time (in seconds) the client waits before
    // determining that a connection attempt to a Sentinel server failed
    'sentinel_timeout' => 0.100,

    // The default number of attempts the client tries to contact a Sentinel
    // server before it determines that it cannot reach all Sentinel servers
    // in a quorum. A value of 0 instructs the client to throw an exception
    // after the first failed attempt, while a value of -1 causes the client
    // to continue to retry connections to Sentinel indefinitely
    'retry_limit' => 20,

    // The default amount of time (in milliseconds) the client waits before
    // attempting to contact another Sentinel server if the previous server did
    // not respond
    'retry_wait' => 1000,

    // Instructs the client to query the first reachable Sentinel server for an
    // updated set of Sentinels each time the client needs to establish a
    // connection with a Redis master or slave server
    'update_sentinels' => false,
],
...
```

Cache, Session, and Queue Drivers
---------------------------------

After configuring the Sentinel database connections, we can instruct Laravel to
use these connections for the application's cache, session, and queue services.
Remember that we don't need to use Sentinel for all of these services. We could
use a standard Redis connection for one and a Sentinel connection for another,
if desired, but we likely want to take advantage of Sentinel for all of our
Redis connections if we use it.

### Cache

Add the following store definition to `config/cache.php` in the `'stores'`
array:

```php
...
'redis-sentinel' => [
    'driver' => 'redis-sentinel',
    'connection' => 'default',
],
...
```

...and set the `CACHE_DRIVER` environment variable to `redis-sentinel` in
`.env`.

If you created a specific connection in the `'redis-sentinel'` database
configuration for the cache, replace `'default'` with the name of the
connection.

### Session

Set the `SESSION_DRIVER` environment variable to `redis-sentinel` in `.env` and
set the `'connection'` directive to `'default'` or the name of the specific
connection you created for storing sessions in the `'redis-sentinel'` database
configuration in `config/session.php`.

### Queue

Add the following connection definition to `config/queue.php` in the
`'connections'` array:

```php
...
'redis-sentinel' => [
    'driver' => 'redis-sentinel',
    'connection' => 'default',
    'queue' => 'default',
    'expire' => 60,
],
...
```

...and set the `QUEUE_DRIVER` environment variable to `redis-sentinel` in
`.env`.

If you created a specific connection in the `'redis-sentinel'` database
configuration for the queue, replace `'default'` with the name of the
connection. If desired, replace the value of `'queue'` with the name of the
queue you'd like to use.


Using Sentinel Connections for Standalone Redis Commands
--------------------------------------------------------

This package adds Redis Sentinel drivers for Laravel's caching, session, and
queue APIs, and the developer may select which of these to use Sentinel
connections for. However, Laravel also provides an API for interacting with
Redis directly through the `Redis` facade, or through
`Illuminate\Redis\Database` which we can resolve through the application
container (`app('redis')`, dependency injection, etc.).

When installed, this package does not impose the use of Sentinel for all Redis
requests. In fact, the developer may choose to use Sentinel connections for
some features and continue to use Laravel's standard Redis connections for
others. By default, this package does not replace Laravel's built-in Redis API.

For example, a developer may decide to use Sentinel connections for the
application's cache and sessions, but directly interact with a single Redis
server using Laravel's standard Redis connections.

That said, this package provides the option to override Laravel's Redis API so
that any Redis commands use the Sentinel connection configuration defined by
the `'redis-sentinel'` database driver.

To use this feature, add the following configuration directive to the root of
the `'redis'` connection definition in `config/database.php`:

```php
'redis' => [
    ...
    'redis_driver' => env('REDIS_DRIVER', 'default'),
    ...
],
```

...and add the environment variable `REDIS_DRIVER` to `sentinel` in `.env`.

When enabled, Redis commands executed through the `Redis` facade or the `redis`
service (`app('redis')`, etc) will operate using the Sentinel connections.

This makes it easier for developers to use a standalone Redis server in their
local environments and switch to a full Sentinel set of servers in production.

Connecting to Sentinel Directly
-------------------------------

If a developer wishes to send Redis commands to a Sentinel server directly,
like, for example, through the `Redis` facade, but doesn't want to override
Laravel's Redis API as above, he or she can use the `RedisSentinel` facade
provided by this package or resolve the database driver from the application
container:

```php
// Uses the 'some-connection' connection defined in the 'redis-sentinel'
// database driver configuration:
RedisSentinel::connection('some-connection')->get('some-key');
app('redis-sentinel')->connection('some-connection')->get('some-key');

// Uses the 'some-connection' connection defined in the standard 'redis'
// database driver configuration:
Redis::connection('some-connection')->get('some-key');
app('redis')->connection('some-connection')->get('some-key');
```

This provides support for uncommon use cases for which an application may need
to connect to both standard Redis servers and Sentinel clusters. We recommend
the approach described in the previous section to uniformly use Sentinel for
the entire application when possible.

To use the facade, add the following alias to the `'aliases'` array in
`config/app.php`:

```php
...
'RedisSentinel' => Monospice\LaravelRedisSentinel\RedisSentinel::class,
...
```

Testing
-------

This package includes a PHPUnit test suite with unit tests for the package's
classes. Because Predis and Laravel both contain full test suites, and because
our code simply wraps these libraries, this package does not perform
functional/integration tests against running Redis or Sentinel servers. We may
add these types of tests in the future if needed.

```
$ phpunit
```

License
-------

The MIT License (MIT). Please see the [LICENSE File](LICENSE) for more
information.

[laravel]: https://laravel.com
[redis]: http://redis.io
[sentinel]: http://redis.io/topics/sentinel
[predis]: https://github.com/nrk/predis
[predis-docs]: https://github.com/nrk/predis/wiki
[laravel-redis-docs]: https://laravel.com/docs/redis
