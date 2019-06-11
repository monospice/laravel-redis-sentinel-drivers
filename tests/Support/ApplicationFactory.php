<?php

namespace Monospice\LaravelRedisSentinel\Tests\Support;

use Illuminate\Broadcasting\BroadcastServiceProvider;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Console\Kernel as ConsoleContract;
use Illuminate\Contracts\Debug\ExceptionHandler as HandlerContract;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Console\Kernel as Console;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Redis\RedisServiceProvider;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\View\ViewServiceProvider;
use Laravel\Horizon\HorizonServiceProvider;
use Monospice\LaravelRedisSentinel\Manager;

/**
 * Bootstraps Laravel and Lumen application instances based for the version of
 * the framework installed for testing.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     http://github.com/monospice/laravel-redis-sentinel-drivers
 */
class ApplicationFactory
{
    const APP_PATH = __DIR__ . '/../../build/app';

    /**
     * Bootstrap a Laravel or Lumen application to run tests against.
     *
     * @param bool $configure If TRUE, configure default application components
     * for tests
     *
     * @return \Illuminate\Contracts\Container\Container The appropriate
     * application instance for the framework under test
     */
    public static function make($configure = true)
    {
        if (static::isLumen()) {
            return static::makeLumenApplication($configure);
        }

        return static::makeLaravelApplication($configure);
    }

    /**
     * Bootstrap a Laravel or Lumen application with Artisan console command
     * support.
     *
     * @param bool $configure If TRUE, configure default application components
     * for tests
     *
     * @return \Illuminate\Contracts\Container\Container The appropriate
     * application instance for the framework under test
     */
    public static function makeForConsole($configure = true)
    {
        $app = static::make();
        $app->config->set('app.env', 'testing');

        $app->register(BusServiceProvider::class);
        $app->singleton(ConsoleContract::class, Console::class);
        $app->singleton(HandlerContract::class, ExceptionHandler::class);

        $app->bootstrapWith([ ]);

        $app->make(ConsoleContract::class)->bootstrap();

        return $app;
    }

    /**
     * Get the version of the Laravel framework used by the package under test.
     *
     * @return string The version string of the framework
     */
    public static function getApplicationVersion()
    {
        if (static::isLumen()) {
            return substr(static::makeLumenApplication(false)->version(), 7, 3);
        }

        return \Illuminate\Foundation\Application::VERSION;
    }

    /**
     * Bootstrap a Laravel application instance to run tests against.
     *
     * @param bool $configure If TRUE, configure default application components
     * for tests
     *
     * @return \Illuminate\Foundation\Application The Laravel application
     * instance
     */
    public static function makeLaravelApplication($configure = true)
    {
        $app = new \Illuminate\Foundation\Application(self::APP_PATH);

        $app->config = new ConfigRepository();

        $app->register(new BroadcastServiceProvider($app));
        $app->register(new CacheServiceProvider($app));
        $app->register(new QueueServiceProvider($app));
        $app->register(new SessionServiceProvider($app));
        $app->register(new RedisServiceProvider($app));

        Facade::setFacadeApplication($app);

        return $app;
    }

    /**
     * Bootstrap a Lumen application instance to run tests against.
     *
     * @param bool $configure If TRUE, configure default application components
     * for tests
     *
     * @return \Laravel\Lumen\Application The Lumen application instance
     */
    public static function makeLumenApplication($configure = true)
    {
        // Set the base path so the application doesn't attempt to use the
        // package's config directory as the application config directory
        // during tests:
        $app = new \Laravel\Lumen\Application(self::APP_PATH);
        $app->instance('path.storage', self::APP_PATH . '/storage');

        if ($configure) {
            $app->register(RedisServiceProvider::class);
            $app->configure('database');
            $app->configure('broadcasting');
            $app->configure('cache');
            $app->configure('queue');
        }

        Facade::setFacadeApplication($app);

        return $app;
    }

    /**
     * Sets up some boilerplate so Horizon can boot for integration tests.
     *
     * @param \Illuminate\Foundation\Application $app The application instance
     * to reconfigure.
     *
     * @return void
     */
    public static function configureHorizonComponents($app)
    {
        $app->config->set('database.redis.default', [ ]);
        $app->config->set('view.paths', [ ]);

        $app->register(FilesystemServiceProvider::class);
        $app->register(ViewServiceProvider::class);
        $app->register(HorizonServiceProvider::class);
    }

    /**
     * Determine if the test is running against a Laravel or Lumen framework.
     *
     * @return bool True if running the test against Lumen
     */
    public static function isLumen()
    {
        return class_exists('Laravel\Lumen\Application');
    }

    /**
     * Determine whether Horizon is installed for testing.
     *
     * @return bool True if we can test against Horizon.
     */
    public static function isHorizonAvailable()
    {
        return class_exists('Laravel\Horizon\Horizon');
    }

    /**
     * Determine whether the application supports the boot() method.
     *
     * @return bool True if any supported Laravel version OR Lumen 5.7+.
     */
    public static function supportsBoot()
    {
        return ! static::isLumen()
            || version_compare(static::getApplicationVersion(), '5.7', 'ge');
    }

    /**
     * Get the fully-qualified class name of the RedisSentinelManager class
     * for the current version of Laravel or Lumen under test.
     *
     * @return string The class name of the appropriate RedisSentinelManager
     * with its namespace
     */
    public static function getVersionedRedisSentinelManagerClass()
    {
        $appVersion = static::getApplicationVersion();

        if (static::isLumen()) {
            $frameworkVersion = '5.4';
        } else {
            $frameworkVersion = '5.4.20';
        }

        if (version_compare($appVersion, $frameworkVersion, 'lt')) {
            return Manager\Laravel540RedisSentinelManager::class;
        }

        return Manager\Laravel5420RedisSentinelManager::class;
    }

    /**
     * Create the minimum application directory tree needed to perform Horizon
     * integration tests.
     *
     * @return void
     */
    public static function makeAppDirectorySkeleton()
    {
        $tree = [
            '/bootstrap/cache',
            '/config',
            '/storage',
        ];

        foreach ($tree as $path) {
            if (! file_exists(self::APP_PATH . $path)) {
                mkdir(self::APP_PATH . $path, 0755, true);
            }
        }
    }
}
