Laravel Drivers for Redis Sentinel
==================================

[![Build Status][travis-badge]][travis]
[![Scrutinizer Code Quality][scrutinizer-badge]][scrutinizer]
[![Latest Stable Version][packagist-stable-badge]][packagist-stable]
[![Total Downloads][packagist-downloads-badge]][packagist-stable]
[![License][license-badge]](#license)

#### Redis Sentinel integration for Laravel and Lumen.

[Redis Sentinel][sentinel] facilitates high-availability, monitoring, and
load-balancing for Redis servers configured for master-slave replication.
[Laravel][laravel] includes built-in support for Redis, but we cannot configure
Sentinel setups flexibly out-of-the-box. This limits configuration of Sentinel
to a single service.

For instance, if we wish to use Redis behind Sentinel for both the cache and
session in Laravel's API, we cannot set separate Redis databases for for both
types of data like we can in a standard, single-server Redis setup without
Sentinel. This causes issues when we need to clear the cache, because Laravel
erases stored session information as well.

This package wraps the configuration of Laravel's broadcasting, cache, session,
and queue APIs for Sentinel with the ability to set options for our Redis
services independently. It adds Sentinel support for [Laravel Horizon][s-horizon]
and fixes other compatibility issues.

We configure the package separately from Laravel's standard Redis configuration
so we can choose to use the Sentinel connections as needed by the environment.
A developer may use a standalone Redis server in their local environment, while
production environments operate a Redis Sentinel set of servers.


Contents
--------

 - [Quickstart **(TL;DR)**][s-quickstart]
 - [Requirements](#requirements)
 - [Installation](#installation)
 - [Configuration Options](#configuration)
     - [Environment-Based Configuration][s-env-config]
     - [Standard Laravel Configuration Files][s-standard-config]
     - [Package Configuration File][s-package-config]
     - [Hybrid Configuration][s-hybrid-config]
 - [Override the Standard Redis API][s-override-redis-api]
 - [Executing Redis Commands (RedisSentinel Facade)][s-facade]
     - [Dependency Injection][s-dependency-injection]
 - [Other Sentinel Considerations][s-considerations]
 - [Laravel Horizon][s-horizon]
 - [Testing](#testing)
 - [License](#license)
 - [Appendix: Environment Variables][s-appx-env-vars]
 - [Appendix: Configuration Examples][s-appx-examples]


Requirements
------------

 - PHP 5.4 or greater
 - [Redis][redis] 2.8 or greater (for Sentinel support)
 - [Predis][predis] 1.1 or greater (for Sentinel client support)
 - [Laravel][laravel] or [Lumen][lumen] 5.0 or greater (4.x doesn't support the
   required Predis version)

**Note:** Laravel 5.4 introduced the ability to use the [PhpRedis][php-redis]
extension as a Redis client for the framework. This package does not yet
support the PhpRedis option.

This Readme assumes prior knowledge of configuring [Redis][redis] for [Redis
Sentinel][sentinel] and [using Redis with Laravel][laravel-redis-docs] or
[Lumen][lumen-redis-docs].


Installation
------------

We're using Laravel, so we'll install through Composer, of course!

#### For Laravel/Lumen 5.4 and above:

```
composer require monospice/laravel-redis-sentinel-drivers
```

#### For Laravel/Lumen 5.3 and below:

```
composer require monospice/laravel-redis-sentinel-drivers:^1.0
```

**Note:** According to the Laravel release schedule, all Laravel versions prior
to 5.4 exited their active development and support periods in August of 2017.
After December, 2017, this package will no longer provide feature releases on
the `1.x` branch for Laravel versions earlier than 5.4.

If the project does not already use Redis with Laravel, this will install the
[Predis][predis] package as well.

#### Register the Service Provider

Laravel 5.5 brings [package discovery][laravel-package-discovery-docs]! *No
service provider registration required in Laravel 5.5+.*

To use the drivers in Laravel 5.4 and below, add the package's service provider
to *config/app.php*:

```php
'providers' => [
    ...
    Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider::class,
    ...
],
```

In Lumen, register the service provider in *bootstrap/app.php*:

```php
$app->register(Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider::class);
```


Quickstart (TL;DR)
------------------

After [installing](#installation) the package, set the following in *.env*:

```shell
CACHE_DRIVER=redis-sentinel
SESSION_DRIVER=redis-sentinel
QUEUE_CONNECTION=redis-sentinel  # Laravel >= 5.7
QUEUE_DRIVER=redis-sentinel      # Laravel <= 5.6
REDIS_DRIVER=redis-sentinel

REDIS_HOST=sentinel1.example.com, sentinel2.example.com, 10.0.0.1, etc.
REDIS_PORT=26379
REDIS_SENTINEL_SERVICE=mymaster  # or your Redis master group name

REDIS_CACHE_DATABASE=1
REDIS_SESSION_DATABASE=2
REDIS_QUEUE_DATABASE=3
```

The following should now use Redis Sentinel connections:

```php
Redis::get('key');
Cache::get('key');
Session::get('key');
Queue::push(new Job());
```

This example configures the package [through the environment][s-env-config]. It
[overrides Laravel's standard Redis API][s-override-redis-api] by setting
`REDIS_DRIVER` to `redis-sentinel`. See [appendix][s-appx-env-vars] for all of
the configurable environment variables. Optionally, enable the [`RedisSentinel`
facade][s-facade].

For those that need a quick development Sentinel server cluster, try the
[*start-cluster.sh*][s-integration-tests] script included with the package's
testing files.


Configuration
-------------

We can configure the package three ways depending on the needs of the
application:

 - [Environment-Based Configuration][s-env-config]
 - [Standard Laravel Configuration Files][s-standard-config]
 - [Package Configuration File][s-package-config]

A [hybrid configuration][s-hybrid-config] uses two or more of these methods.

With the release of version 2.2.0, the package supports simple configuration
through environment variables with a default configuration structure suitable
for many applications. This especially relieves Lumen users of the need to
create several config files that may not already exist with an initial Lumen
installation.

The package continues to support advanced configuration through standard config
files without requiring changes for existing projects.


Environment-Based Configuration
-------------------------------

For suitable applications, the package's ability to configure itself from the
environment eliminates the need to create or modify configuration files in many
scenarios. The package automatically configures Redis Sentinel connections and
the application broadcasting, cache, session, and queue services for these
connections using environment variables.

Developers may still configure the package [through standard Laravel
configuration files][s-standard-config] when the application requirements
exceed the package's automatic configuration capacity.

Typically, we assign the application environment variables in the project's
[*.env* file][laravel-env-docs] during development. The configuration for a
basic application may be as simple as setting the following values in this
file:

```shell
REDIS_HOST=sentinel.example.com
REDIS_PORT=26379
REDIS_SENTINEL_SERVICE=mymaster
```

This sets up the *default* Redis Sentinel connection for the package's services
that we can access through the [`RedisSentinel` facade][s-facade] (or by
resolving `app('redis-sentinel')` from the container) like we would for
Laravel's [standard Redis API][laravel-redis-api-docs]. To use this Sentinel
connection for Laravel's broadcasting, cache, session, or queue services,
change the following values as well:

```shell
BROADCAST_DRIVER=redis-sentinel
CACHE_DRIVER=redis-sentinel
SESSION_DRIVER=redis-sentinel
QUEUE_CONNECTION=redis-sentinel  # Laravel >= 5.7
QUEUE_DRIVER=redis-sentinel      # Laravel <= 5.6
```

#### Connection-Specific Configuration

In many cases, we'd set different connection parameters for the application
broadcasts, cache, session, and queue. We may configure different Redis
databases for our cache and session (so that clearing the cache doesn't erase
our user session information), and the Redis servers that contain the
application queue may reside behind a different Sentinel service (master group
name):

```shell
REDIS_CACHE_DATABASE=1
REDIS_SESSION_DATABASE=2
REDIS_QUEUE_SERVICE=queue-service
```

#### Specifying Multiple Hosts

To supply multiple hosts for a connection through environment variables, set
the value of any `*_HOST` variable to a comma-seperated string of hostnames or
IP addresses:

```shell
REDIS_HOST=sentinel1.example.com, sentinel2.example.com, ...
REDIS_CACHE_HOST=10.0.0.1, 10.0.0.2, 10.0.0.3
REDIS_QUEUE_HOST=tcp://10.0.0.4:26379, tcp://10.0.0.4:26380, ...
```

Hosts share the port set for the connection unless we explicitly include the
port number after the hostname as shown.

#### Mixed Applications

The previous examples set the `REDIS_HOST` and `REDIS_PORT` variables, which
Laravel also reads to configure standard Redis connections. This enables
developers to [use the same variables][s-dev-vs-prod-example] in development,
with a single Redis server, and in production, with a full set of Sentinel
servers. However, if an application contains code that sends requests to both
Redis and Sentinel connections in the same environment, we must assign one or
more of the Sentinel-specific variables instead:

```shell
REDIS_SENTINEL_HOST=sentinel.example.com
REDIS_SENTINEL_PORT=26379
REDIS_SENTINEL_PASSWORD=secret
REDIS_SENTINEL_DATABASE=0
```

#### Other Environment Configuration Options

We can change the value of `REDIS_DRIVER` to `redis-sentinel` to [override the
standard Laravel Redis API][s-override-redis-api].

For a full list of the environment variables this package consumes, see the
[appendix][s-appx-env-vars]. Check out the package's [internal configuration
file](config/redis-sentinel.php) or the [environment-based configuration
examples][s-env-config-examples] to better understand how the package uses
environment variables.


Using Standard Configuration Files
----------------------------------

In addition to [environment-based configuration][s-env-config], the package
allows developers to configure Redis Sentinel integration through Laravel's
standard configuration files. This option exists for cases when applications
require more advanced or specialized Sentinel configuration than the package's
default environment-based configuration can provide.

For this configuration method, we'll modify the following config files:

 - *config/database.php* - to define the Redis Sentinel connections
 - *config/broadcasting.php* - to define the Redis Sentinel broadcaster
 - *config/cache.php* - to define a Redis Sentinel cache store
 - *config/session.php* - to set the Redis Sentinel connection for sessions
 - *config/queue.php* - to define a Redis Sentinel queue connection

**Note:** Lumen users may [create a package config file][s-package-config]
instead to avoid the need to create all of the above files if they don't exist.

When [environment-based configuration][s-env-config] satisfies the needs of the
application, we do not need to modify any config files. The code illustrated
in the following sections [overrides][s-hybrid-config] the package's automatic
configuration.

### Redis Sentinel Connection Configuration

We'll configure the Redis Sentinel database connections separately from
Laravel's default Redis database connection. This enables us to use Laravel's
standard Redis functionality side-by-side if needed, such as if a developer
runs a single Redis server in their local environment, while the production
environment operates a full set of Redis and Sentinel servers. We don't need to
remove the `'redis'` driver config block that ships with Laravel by default.

**Note:** Laravel passes these configuration options to the [Predis][predis]
client library, so we can include advanced configuration options here if
needed. See the [Predis documentation][predis-docs] for more information.

#### Basic Configuration

For a simple setup with a single Sentinel server, add the following block to
*config/database.php* for the `'redis-sentinel'` database driver.

```php
'redis-sentinel' => [

    'default' => [
        [
            'host' => env('REDIS_HOST', 'localhost'),
            'port' => env('REDIS_PORT', 26379),
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
example above to *.env*.

#### Advanced Configuration

The configuration block above is almost a drop-in replacement for Laravel's
built-in `'redis'` connection configuration that we could use to configure an
application for Sentinel without this package. However, we cannot configure
Laravel's standard Redis connections for anything more complex than this basic
Sentinel setup because of limitations in the way Laravel parses its Redis
configuration. A single Sentinel server or connection is typically insufficient
for highly-available or complex applications. Read the [examples in the
appendix][s-appx-examples] for more robust configuration instructions:

 - [Connections with Multiple Sentinel Hosts][s-multi-sentinel-example]
 - [Multiple Service-Specific Connections][s-multi-service-example]


#### Other Sentinel Connection Options

The Predis client supports some additional configuration options that determine
how it handles connections to Sentinel servers. We can add these to the global
`'options'` array for all Sentinel connections or to a local `'options'` array
for a single connection. The default values are shown below:

```php
'options' => [
    ...

    // The default amount of time (in seconds) the client waits before
    // determining that a connection attempt to a Sentinel server failed.
    'sentinel_timeout' => 0.100,

    // The default number of attempts to retry a command when the client fails
    // to connect to a Redis or Sentinel server. A value of 0 instructs the
    // client to throw an exception after the first failed attempt, while a
    // value of -1 causes the client to continue to retry commands indefinitely.
    'retry_limit' => 20,

    // The default amount of time (in milliseconds) that the client waits before
    // attempting to contact another Sentinel server or retry a command if the
    // previous server did not respond.
    'retry_wait' => 1000,

    // Instructs the client to query the first reachable Sentinel server for an
    // updated set of Sentinels each time the client needs to establish a
    // connection with a Redis master or replica.
    'update_sentinels' => false,
],
```

### Broadcasting, Cache, Session, and Queue Drivers

After configuring the Sentinel database connections, we can instruct Laravel to
use these connections for the application's other redis-enabled services.
Remember that we don't need to set Sentinel connections for all of these
services. We could select a standard Redis connection for one and a Sentinel
connection for another, if desired, but we likely want to take advantage of
Sentinel for all of our Redis connections if it's available.

**Note:** We can omit (or remove) the following configuration blocks entirely
and the package will configure these services for us. If we created custom
Sentinel connections as above, we may need to [declare those connection
names](#broadcast_redis_connection-broadcast_redis_sentinel_connection).

#### Broadcasting

Add the following connection definition to *config/broadcasting.php* in the
`'connections'` array:

```php
'connections' => [
    ...
    'redis-sentinel' => [
        'driver' => 'redis-sentinel',
        'connection' => 'default',
    ],
],
```

...and change the `BROADCAST_DRIVER` environment variable to `redis-sentinel`
in *.env*.

If the application contains a specific connection in the `'redis-sentinel'`
database configuration for the event broadcasting, replace `'default'` with its
name.

#### Cache

Add the following store definition to *config/cache.php* in the `'stores'`
array:

```php
'stores' => [
    ...
    'redis-sentinel' => [
        'driver' => 'redis-sentinel',
        'connection' => 'default',
    ],
],
```

...and change the `CACHE_DRIVER` environment variable to `redis-sentinel` in
*.env*.

If the application contains a specific connection in the `'redis-sentinel'`
database configuration for the cache, replace `'default'` with its name.

#### Session

Change the `SESSION_DRIVER` environment variable to `redis-sentinel` in *.env*.
Then, in *config/session.php*, set the `'connection'` directive to `'default'`,
or the name of the specific connection created for storing sessions from the
`'redis-sentinel'` database configuration.

#### Queue

Add the following connection definition to *config/queue.php* in the
`'connections'` array:

```php
'connections' => [
    ...
    'redis-sentinel' => [
        'driver' => 'redis-sentinel',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 90, // Laravel >= 5.4.30
        'expire' => 90,      // Laravel < 5.4.30
    ],
],
```

...and change the `QUEUE_CONNECTION` (Laravel 5.7+) or `QUEUE_DRIVER` (Laravel
<= 5.6) environment variable to `redis-sentinel` in *.env*.

If the application contains a specific connection in the `'redis-sentinel'`
database configuration for the queue, replace `'connection' => 'default'` with
its name.


Using a Package Configuration File
----------------------------------

Lumen projects don't include configuration files by default. Instead, by
convention, Lumen reads configuration information from the environment. If we
wish to configure this package through config files as described in the
[previous section][s-standard-config], rather than using the [environment-based
configuration][s-env-config], we can add a single package configuration file:
*config/redis-sentinel.php*. This alleviates the need to create several
standard config files in Lumen.

The package configuration file contains elements that the package merges back
into the main configuration locations at runtime. To illustrate, when the
custom *redis-sentinel.php* file contains:

```php
return [
    'database' =>
        'redis-sentinel' => [ /* ...Redis Sentinel connections... */ ]
    ]
];
```

...the package will set the `database.redis-sentinel` configuration value from
the value of `redis-sentinel.database.redis-sentinel` when the application
boots [unless the key already exists][s-hybrid-config].

We can customize the package's [internal config file](config/redis-sentinel.php)
by copying it into our project's *config/* directory  and changing the values
as needed. Lumen users may need to create this directory if it doesn't exist.

A custom package config file needs only to contain the top-level elements that
developer wishes to customize: in the code shown above, the custom config file
only overrides the package's default configuration for Redis Sentinel
connections, so the package will still automatically configure the broadcasting,
cache, session, and queue services using environment variables.


Hybrid Configuration
--------------------

Although unnecessary in most cases, developers may combine two or more of the
configuration methods provided by this package. For example, an application may
contain a [standard][s-standard-config] or [package][s-package-config] config
file that defines the Redis Sentinel connections, but rely on the package's
automatic [environment-based configuration][s-env-config] to set up the cache,
session, queue, and broadcasting services for Sentinel.

The package uses configuration data in this order of precedence:

 1. Standard Laravel configuration files
 2. A custom package configuration file
 3. Automatic environment-based configuration

This means that package-specific values in the standard config files override
values in a custom package config file, which, in turn, override the package's
default automatic configuration through environment variables. In other words,
a custom package config file inherits the values from the package's default
configuration that it does not explicitly declare, and the main application
configuration receives the values from both of these that it does not provide
in a standard config file.


Override the Standard Redis API
-------------------------------

This package adds Redis Sentinel drivers for Laravel's caching, session, and
queue APIs, and the developer may select which of these to utilize Sentinel
connections for. However, Laravel also provides [an API][laravel-redis-api-docs]
for interacting with Redis directly through the `Redis` facade, or through
the Redis connection manager which we can resolve through the application
container (`app('redis')`, dependency injection, etc.).

When installed, this package does not impose the use of Sentinel for all Redis
requests. In fact, we can choose to use Sentinel connections for some features
and continue to use Laravel's standard Redis connections for others. By
default, this package does not replace Laravel's built-in Redis API.

As an example, we may decide to use Sentinel connections for the application's
cache and sessions, but directly interact with a single Redis server using
Laravel's standard Redis connections.

That said, this package provides the option to override Laravel's Redis API so
that any Redis commands use the Sentinel connection configuration defined by
the `'redis-sentinel'` database driver.

To use this feature, add the following configuration directive to the root of
the `'redis'` connection definition in *config/database.php* (if not using
[environment-based configuration][s-env-config]):

```php
'redis' => [
    ...
    'driver' => env('REDIS_DRIVER', 'default'),
    ...
],
```

...and add the environment variable `REDIS_DRIVER` to *.env* with the value
`redis-sentinel`.

When enabled, Redis commands executed through the `Redis` facade or the `redis`
service (`app('redis')`, etc) will operate using the Sentinel connections.

This makes it easier for developers to use a standalone Redis server in their
local environments and switch to a full Sentinel set of servers in production.

**Note:** When using the package with [Laravel Horizon][s-horizon], this change
will cause Horizon to run over a Sentinel connection as well.


Executing Redis Commands (RedisSentinel Facade)
-------------------------------------------------

If we need to send Redis commands to Redis instances behind a Sentinel server
directly, such as we can through the `Redis` facade, but we don't want to
[override Laravel's Redis API][s-override-redis-api] as above, we can use the
`RedisSentinel` facade provided by this package or resolve the `redis-sentinel`
service from the application container:

```php
// Uses the 'default' connection defined in the 'redis-sentinel' config block:
RedisSentinel::get('some-key');
app('redis-sentinel')->get('some-key');

// Uses the 'default' connection defined in the standard 'redis' config block:
Redis::get('some-key');
app('redis')->get('some-key');
```

This provides support for uncommon use cases for which an application may need
to connect to both standard Redis servers and Sentinel clusters in the same
environment. When possible, follow the approach described in the previous
section to uniformly connect to Sentinel throughout the application to decouple
the code from the Redis implementation.

The facade is not auto-aliased in Laravel 5.5+ for future compatibility with
the PhpRedis extension. To enable the facade in Laravel, add the following
alias to the `'aliases'` array in *config/app.php*:

```php
'aliases' => [
    ...
    'RedisSentinel' => Monospice\LaravelRedisSentinel\RedisSentinel::class,
    ...
],
```

In Lumen, add the alias to *bootstrap/app.php*:

```php
class_alias('Monospice\LaravelRedisSentinel\RedisSentinel', 'RedisSentinel');
```

### Dependency Injection

For those that prefer to declare the Redis Sentinel manager as a dependency of
a class rather than using the facade, we can type-hint the interface that the
container will resolve when building an object from the container:

```php
use Monospice\LaravelRedisSentinel\Contracts\Factory as RedisSentinel;
...
public function __construct(RedisSentinel $sentinel)
{
    $sentinel->get('some-key');
}
```

The above explicitly requests an instance of the package's Sentinel service. If
we [override the Redis API][s-override-redis-api], we can use the standard
Redis contract instead, and the application will inject the appropriate service
based on the configuration:

```php
use Illuminate\Contracts\Redis\Factory as Redis;
...
public function __construct(Redis $redis)
{
    // Either a Sentinel connection or a standard Redis connection depending on
    // the value of REDIS_DRIVER or config('database.redis.driver'):
    $redis->get('some-key');
}
```


Other Sentinel Considerations
-----------------------------

The following sections describe some characteristics to keep in mind when
working with Sentinel connections.

### Read and Write Operations

To spread load between available resources, the client attempts to execute read
operations on Redis slave servers when initializing a connection. Commands that
write data will always execute on the master server.

### Transactions

All commands in a transaction, even read-only commands, execute on the master
Redis server. When it makes sense to do so, avoid calling read commands within
a transaction to improve load-balancing.

If a transaction aborts because of a connection failure, the package attempts
to reconnect and retry the transaction until it exhausts the configured number
of allowed attempts (`retry_limit`), or until the entire transaction succeeds.

**Important:** Predis provides a specialized MULTI/EXEC abstraction that we can
obtain by calling `transaction()` with no arguments. This API is *not*
protected by Sentinel connection failure handling. For high-availability, use
the Laravel API by passing a closure to `transaction()`.

### Publish/Subscribe

For PUB/SUB messaging, the client publishes messages to the master server. When
subscribing, the package attempts to connect to a slave server first before
falling-back to the master. Like with read operations, this helps to distribute
the load away from the master because messages published to the master propagate
to each of the slaves.

Applications with long-running subscribers need to extend the timeout of the
connection or disable it by setting `read_write_timeout` to `0`. Additionally,
we also need to extend or disable the `timeout` configuration directive on the
Redis servers that the application subscribes to.

When a subscriber connection fails, the package will attempt to reconnect to
another server and resume listening for messages. We may want to set the value
of `retry_limit` to `-1` on connections with long-running subscribers so that
the client continues to retry forever. Note that a subscriber may miss messages
published to a channel while re-establishing the connection.

**Important:** Predis provides a PUB/SUB consumer that we can obtain by calling
`pubSubLoop()` with no arguments. This API is *not* protected by Sentinel
connection failure handling. For high-availability, use the Laravel API by
passing a closure to `subscribe()` or `psubscribe()`.


Laravel Horizon
---------------

Versions 2.4.0 and greater of this package provide for the use of Sentinel
connections with the [Laravel Horizon][laravel-horizon] queue management tool
in compatible applications (Laravel 5.5+).

After [installing Horizon][laravel-horizon-install-docs], we need to update
some configuration settings in *config/horizon.php*:

If needed, change `'use' => 'default'` to the name of the Sentinel connection
to use for the Horizon backend as configured in *config/database.php*.

**Important:** The standard `'redis'` connections array in *config/database.php*
must contain an element with the same key as the Sentinel connection specified
for the `'use'` directive or Horizon throws an exception. Currently, Horizon
does not provide a way for this package to handle this behavior, but an
(in-progress) pull request may eliminate this requirement in the future. This
element can contain any value (but a matching Redis connection config seems
most appropriate).

Change the backend driver for Horizon's internal metadata to `'redis-sentinel'`
by adding the following element to the top-level array:

```php
'driver' => env('HORIZON_DRIVER', 'redis');
```

...and assign the value of `redis-sentinel` to `HORIZON_DRIVER` in *.env*.


Then, add an entry to the `'waits'` array for any Sentinel queues:

```php
'waits' => [
    ...
    'redis-sentinel:default' => 60,
],
```

Next, change the connection driver to `redis-sentinel` for each of the queue
workers that should use Sentinel connections in the `'environments'` block:

```php
...
'supervisor-1' => [
    'connection' => 'redis-sentinel',
    ...
],
...
```

Horizon will now use the application's Redis Sentinel connections to monitor
and process our queues.

**Note:** If we already configured the package to [override Laravel's standard
Redis API][s-override-redis-api] (by setting `REDIS_DRIVER` to `redis-sentinel`,
for example), we don't need to change `HORIZON_DRIVER` to `'redis-sentinel'`.
The package already routes all Redis operations through Sentinel connections.


Testing
-------

This package includes a PHPUnit test suite with unit tests for the package's
classes and an integration test suite for Sentinel-specific functionality and
compatibility fixes. These tests do not verify every Redis command because
Predis and Laravel both contain full test suites themselves, and because the
package code simply wraps these libraries.

```shell
$ phpunit --testsuite unit
$ phpunit --testsuite integration
```

The unit tests do not require live Redis servers. Read the next section for
integration testing environment suggestions.

**Note:** Composer does not download this package's testing files with a normal
installation. We need to clone the package repository directly or install it
with the `--prefer-source` option.

### Integration Tests

This package's integration test suite validates Sentinel- and Redis-specific
functionality against real servers. These tests require at least one Sentinel
server that monitors a Redis master. Additionally, at least one replica should
synchronize with the master for optimal test coverage. Developers may supply
their own servers or start an environment using the package's tools described
below.

To customize the Sentinel connection settings used by the integration tests,
copy *phpunit.xml.dist* to *phpunit.xml* and change the constants defined in
the `<php>...</php>` block.

We can run the [*start-cluster.sh*](start-cluster.sh) script provided in the
project's root directory to spin up Redis and Sentinel servers for a testing
environment. Read the script help page for usage information.

```shell
$ ./start-cluster.sh help
```

Docker users may wish to use the script to start testing servers in a container:

```shell
$ docker run --name redis-sentinel \
    -v "$(pwd):/project" \
    -w /project \
    -u "$(id -u):$(id -g)" \
    -e BIND_ADDRESS=0.0.0.0 \
    -e SENTINEL_PORTS='26379-26381' \
    -e REDIS_GROUP_1='service1 6379-6381' \
    -e REDIS_GROUP_2='service2 6382-6384' \
    -e LOGGING=yes \
    -p 6379-6384:6379-6384 \
    -p 26379-26381:26379-26381 \
    --entrypoint start-cluster.sh \
    redis:alpine
```

The package provides a [Compose file](docker-compose.yml) with the same options
for running tests:

```shell
$ export CONTAINER_USER_ID="$(id -u):$(id -g)"
$ docker-compose up -d cluster
$ docker-compose run --rm tests [--testsuite ...]
```

Developers can also customize the Compose file by copying *docker-compose.yml*
to *docker-compose.override.yml*.


License
-------

The MIT License (MIT). Please see the [LICENSE File](LICENSE) for more
information.

-------------------------------------------------------------------------------


Appendix: Environment Variables
-------------------------------

The package consumes the following environment variables when using the default
[environment-based configuration][s-env-config]. Developers only need to supply
values for the variables that apply to their particular application and Redis
setup. The default values are sufficient in most cases.

### `REDIS_{HOST,PORT,PASSWORD,DATABASE}`

The basic connection parameters used by default for all Sentinel connections.

To simplify environment configuration, this package attempts to read both the
`REDIS_*` and the `REDIS_SENTINEL_*` environment variables that specify values
shared by multiple Sentinel connections. If an application does not execute
commands through both Redis Sentinel and standard Redis connections [at the same
time][s-facade], this feature allows developers to use the same environment
variale names in development (with a single Redis server) and in production
(with a full set of Sentinel servers).

 - `REDIS_HOST` - One [or more][s-multiple-hosts] hostnames or IP
   addresses. Defaults to `localhost` when unset.
 - `REDIS_PORT` - The listening port of the Sentinel servers. Defaults to
   `26379` for Sentinel connections when unset.
 - `REDIS_PASSWORD` - The password, if any, used to authenticate with the Redis
   servers *behind* Sentinel (Sentinels don't support password auth themselves).
 - `REDIS_DATABASE` - The number of the database to select when issuing commands
   to the Redis servers behind Sentinel (`0` to `15` in a normal Redis config).
   Defaults to `0`.

### `REDIS_SENTINEL_{HOST,PORT,PASSWORD,DATABASE}`

Set these variables instead of the above when the application uses both standard
Redis and Redis Sentinel connections at the same time.

 - `REDIS_SENTINEL_HOST` - See `REDIS_HOST`.
 - `REDIS_SENTINEL_PORT` - See `REDIS_PORT`.
 - `REDIS_SENTINEL_PASSWORD` - See `REDIS_PASSWORD`.
 - `REDIS_SENTINEL_DATABASE` - See `REDIS_DATABASE`.

### `REDIS_SENTINEL_SERVICE`

The Redis master group name (as specified in the Sentinel server configuration
file) that identifies the default Sentinel service used by all Sentinel
connections. Defaults to `mymaster`.

Set `REDIS_CACHE_SERVICE`, `REDIS_SESSION_SERVICE`, or `REDIS_QUEUE_SERVICE` to
override this value for a service-specific connection.

### `REDIS_SENTINEL_{TIMEOUT,RETRY_LIMIT,RETRY_WAIT,DISCOVERY}`

The Predis client supports some additional configuration options that determine
how it handles connections to Sentinel servers.

 - `REDIS_SENTINEL_TIMEOUT` - The amount of time (in seconds) the client waits
   before determining that a connection attempt to a Sentinel server failed.
   Defaults to `0.100`.
 - `REDIS_SENTINEL_RETRY_LIMIT` - The number of attempts the client tries to
   contact a Sentinel server before it determines that it cannot reach all
   Sentinel servers in a quorum. A value of `0` instructs the client to throw
   an exception after the first failed attempt, while a value of `-1` causes
   the client to continue to retry connections to Sentinel indefinitely.
   Defaults to `20`.
 - `REDIS_SENTINEL_RETRY_WAIT` - The amount of time (in milliseconds) the
   client waits before attempting to contact another Sentinel server if the
   previous server did not respond. Defaults to `1000`.
 - `REDIS_SENTINEL_DISCOVERY` - Instructs the client to query the first
   reachable Sentinel server for an updated set of Sentinels each time the
   client needs to establish a connection with a Redis master or slave server.
   Defaults to `false`.

### `REDIS_DRIVER`

Set the value of this variable to `redis-sentinel` to [override Laravel's
standard Redis API][s-override-redis-api].

### `BROADCAST_DRIVER`, `CACHE_DRIVER`, `SESSION_DRIVER`, `QUEUE_CONNECTION`

Laravel uses these to select the backends for the application broadcasting,
cache, session, and queue services. Set the value to `redis-sentinel` for each
service that the application should use Sentinel connections for.

**Note:** Laravel 5.7 renamed `QUEUE_DRIVER` to `QUEUE_CONNECTION` in the
default configuration files.

### `REDIS_{BROADCAST,CACHE,SESSION,QUEUE}_{HOST,PORT,PASSWORD,DATABASE,SERVICE}`

These variables configure service-specific connection parameters when they
differ from the default Sentinel connection parameters for the broadcasting,
cache, session, and queue connections. For example:

 - `REDIS_BROADCAST_HOST` - Overrides `REDIS_HOST` or `REDIS_SENTINEL_HOST` for
   the default *broadcasting* connection.
 - `REDIS_CACHE_PORT` - Overrides `REDIS_PORT` or `REDIS_SENTINEL_PORT` for
   the default *cache* connection.
 - `REDIS_SESSION_PASSWORD` - Overrides `REDIS_PASSWORD` or
   `REDIS_SENTINEL_PASSWORD` for the default *session* connection.
 - `REDIS_QUEUE_DATABASE` - Overrides `REDIS_DATABASE` or
   `REDIS_SENTINEL_DATABASE` for the default *queue* connection.
 - `REDIS_QUEUE_SERVICE` - Overrides `REDIS_SENTINEL_SERVICE` for the default
   *queue* connection.

### `BROADCAST_REDIS_CONNECTION`, `BROADCAST_REDIS_SENTINEL_CONNECTION`

The name of the Sentinel connection to select for application broadcasting when
`BROADCAST_DRIVER` equals `redis-sentinel`. It defaults to the package's
internal, auto-configured *broadcasting* connection when unset.

### `CACHE_REDIS_CONNECTION`, `CACHE_REDIS_SENTINEL_CONNECTION`

The name of the Sentinel connection to select for the application cache when
`CACHE_DRIVER` equals `redis-sentinel`. It defaults to the package's internal,
auto-configured *cache* connection when unset.

### `QUEUE_REDIS_CONNECTION`, `QUEUE_REDIS_SENTINEL_CONNECTION`

The name of the Sentinel connection to select for the application queue when
`QUEUE_CONNECTION` (Laravel 5.7+) or `QUEUE_DRIVER` (Laravel <= 5.6) equals
`redis-sentinel`. It defaults to the package's internal, auto-configured
*queue* connection when unset.

### `SESSION_CONNECTION`

The name of the Sentinel connection to select for storing application sessions
when `SESSION_DRIVER` equals `redis-sentinel`. It defaults to the package's
internal, auto-configured *session* connection when unset unless the
application configuration already contains a value for `session.connection`.

### `REDIS_SENTINEL_AUTO_BOOT`

When set to `true`, this flag instructs the package to boot the package after
it registers its services without waiting for the application boot phase. This
provides a way for applications that use Sentinel connections in other service
providers to initialize the package immediately.

Appendix: Configuration Examples
--------------------------------

 - [Environment-based Configuration Examples][s-env-config-examples]
     - [Development vs. Production][s-dev-vs-prod-example]
 - [Configuration File Examples][s-standard-config-examples]
     - [Multi-Sentinel Configuration][s-multi-sentinel-example]
     - [Multi-Service Configuration][s-multi-service-example]

### Environment-based Configuration Examples

Supplemental examples for [environment-based-configuration][s-env-config].

#### Development vs. Production

This example shows how we might change the values of environment variables
between environments when we run a single Redis server in development and a
full set of Sentinel servers in production.

```shell
# Development:                    # Production:

CACHE_DRIVER=redis                CACHE_DRIVER=redis-sentinel
SESSION_DRIVER=redis              SESSION_DRIVER=redis-sentinel
QUEUE_CONNECTION=redis            QUEUE_CONNECTION=redis-sentinel

REDIS_HOST=localhost              REDIS_HOST=sentinel1, sentinel2, sentinel3
REDIS_PORT=6379                   REDIS_PORT=26379
REDIS_SENTINEL_SERVICE=null       REDIS_SENTINEL_SERVICE=mymaster
```

Don't forget to run the `artisan config:cache` command in production when
possible. This dramatically improves the configuration loading time for the
application and this package.

[Best practice][phpdotenv-usage-notes] suggests that we avoid using the
development *.env* file in production environments. Consider other means to set
environment variables:

> "phpdotenv is made for development environments, and generally should not be
> used in production. In production, the actual environment variables should be
> set so that there is no overhead of loading the .env file on each request.
> This can be achieved via an automated deployment process with tools like
> Vagrant, chef, or Puppet, or can be set manually with cloud hosts..."

### Configuration File Examples

These examples demonstrate how to setup Laravel's standard configuration files
to configure the package for more-advanced setups. For an introduction to using
configuration files, read the [config file documentation][s-standard-config].

#### Multi-Sentinel Connection Configuration

In a true highly-available Redis setup, we'll run more than one Sentinel server
in a quorum. This adds redundancy for a failure event during which one or more
Sentinel servers become unresponsive. We can add multiple Sentinel server
definitions to our `'default'` connection in *config/database.php*:

```php
...
'redis-sentinel' => [

    'default' => [
        [
            'host' => 'sentinel1.example.com',
            'port' => 26379,
        ],
        [
            'host' => 'sentinel2.example.com',
            'port' => 26379,
        ],
        [
            'host' => 'sentinel3.example.com'
            'port' => 26379,
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
```

With this configuration, we declare three Sentinel servers that can handle
requests for our Redis service, `mymaster` (the master group name as specified
in the Sentinel server configuration file). If one of the Sentinel servers
fails, the Predis client will select a different Sentinel server to send
requests to.

Typically, in a clustered environment, we don't want to hard-code each server
into our configuration like above. We may install some form of load balancing
or service discovery to route requests to a Sentinel server through an
aggregate hostname, such as `sentinels.example.com`, for flexible deployment
and arbritrary scaling.

#### Multi-service Connection Configuration

As we mentioned previously, we likely want to separate the Redis connections
Laravel uses for each of our services. For instance, we'd use separate databases
on a Redis server for our cache and session storage. In this example, we may
also want to create a database on a different set of Redis servers managed by
Sentinel for something like a feed. For this setup, we'll configure multiple
`'redis-sentinel'` connections:

```php
...
'redis-sentinel' => [

    'cache' => [
        [
            'host' => env('REDIS_HOST', 'localhost'),
            'port' => env('REDIS_PORT', 26379),
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
            'host' => env('REDIS_HOST', 'localhost'),
            'port' => env('REDIS_PORT', 26379),
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
            'host' => env('REDIS_HOST', 'localhost'),
            'port' => env('REDIS_PORT', 26379),
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
```

Notice that we removed the global `'options'` array and created a local
`'options'` array for each connection. In this example setup, we store the
application cache and sessions on one Redis server set, and feed data in
another set. In the first connection block, we set the Redis database for
storing cache data to `0`, and the database for session data to `1`, which
allows us to clear our application cache without erasing user sessions.

This example setup includes a second set of Redis servers for storing feed data.
The example Sentinel servers contain configuration for the first set with the
service name, `mymaster`, and for the secord set with the service name,
`feed-service`.  The local connection options allow us to specify which service
(Redis master group name) the connection makes requests for. In particular, we
set the service name of the `'feed'` connection to `'feed-service'`.

For more information about Sentinel service configuration, see the [Redis
Sentinel Documentation][sentinel].


[s-appx-env-vars]: #appendix-environment-variables
[s-appx-examples]: #appendix-configuration-examples
[s-considerations]: #other-sentinel-considerations
[s-dependency-injection]: #dependency-injection
[s-dev-vs-prod-example]: #development-vs-production
[s-env-config]: #environment-based-configuration
[s-env-config-examples]: #environment-based-configuration-examples
[s-facade]: #executing-redis-commands-redissentinel-facade
[s-horizon]: #laravel-horizon
[s-hybrid-config]: #hybrid-configuration
[s-integration-tests]: #integration-tests
[s-multi-sentinel-example]: #multi-sentinel-connection-configuration
[s-multi-service-example]: #multi-service-connection-configuration
[s-multiple-hosts]: #specifying-multiple-hosts
[s-override-redis-api]: #override-the-standard-redis-api
[s-package-config]: #using-a-package-configuration-file
[s-quickstart]: #quickstart-tldr
[s-standard-config]: #using-standard-configuration-files
[s-standard-config-examples]: #configuration-file-examples

[laravel-env-docs]: https://laravel.com/docs/configuration#environment-configuration
[laravel-horizon]: https://horizon.laravel.com
[laravel-horizon-docs]: https://laravel.com/docs/horizon
[laravel-horizon-install-docs]: https://laravel.com/docs/horizon#installation
[laravel-package-discovery-docs]: https://laravel.com/docs/packages#package-discovery
[laravel-redis-api-docs]: https://laravel.com/docs/redis#interacting-with-redis
[laravel-redis-docs]: https://laravel.com/docs/redis
[laravel]: https://laravel.com
[license-badge]: https://poser.pugx.org/monospice/laravel-redis-sentinel-drivers/license
[lumen-redis-docs]: https://lumen.laravel.com/docs/cache
[lumen]: https://lumen.laravel.com
[packagist-downloads-badge]: https://poser.pugx.org/monospice/laravel-redis-sentinel-drivers/downloads
[packagist-stable]: https://packagist.org/packages/monospice/laravel-redis-sentinel-drivers
[packagist-stable-badge]: https://poser.pugx.org/monospice/laravel-redis-sentinel-drivers/v/stable
[php-redis]: https://github.com/phpredis/phpredis
[phpdotenv-usage-notes]: https://github.com/vlucas/phpdotenv#usage-notes
[predis-docs]: https://github.com/nrk/predis/wiki
[predis]: https://github.com/nrk/predis
[redis]: https://redis.io
[scrutinizer-badge]: https://scrutinizer-ci.com/g/monospice/laravel-redis-sentinel-drivers/badges/quality-score.png?b=2.x
[scrutinizer]: https://scrutinizer-ci.com/g/monospice/laravel-redis-sentinel-drivers/?branch=2.x
[sentinel]: https://redis.io/topics/sentinel
[travis-badge]: https://travis-ci.org/monospice/laravel-redis-sentinel-drivers.svg?branch=2.x
[travis]: https://travis-ci.org/monospice/laravel-redis-sentinel-drivers
