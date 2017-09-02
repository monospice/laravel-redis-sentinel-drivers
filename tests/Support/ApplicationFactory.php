<?php

namespace Monospice\LaravelRedisSentinel\Tests\Support;

use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Redis\RedisServiceProvider;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Str;

class ApplicationFactory
{
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
        $app = new \Illuminate\Foundation\Application();
        $app->register(new CacheServiceProvider($app));
        $app->register(new QueueServiceProvider($app));
        $app->register(new SessionServiceProvider($app));
        $app->register(new RedisServiceProvider($app));

        $app->config = new ConfigRepository();

        static::bootstrapEncryption($app);

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
        $app = new \Laravel\Lumen\Application(__DIR__ . '/..');

        if ($configure) {
            $app->configure('database');
            $app->configure('cache');
            $app->configure('queue');

            static::bootstrapEncryption($app);

            // Redis is not part of Lumen's default bindings after version 5.1:
            if (static::getApplicationVersion() > 5.1) {
                $app->register(new RedisServiceProvider($app));
            }
        }

        return $app;
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
     * Set up application encryption services for testing queue in framework
     * versions where queue services depend on encryption.
     *
     * @param \Illuminate\Contracts\Container\Container $app The application
     * instance to bootstrap encryption for
     *
     * @return void
     */
    private static function bootstrapEncryption($app)
    {
        $version = static::getApplicationVersion();

        // For running tests against Laravel Framework < 5.3, we need to set
        // up encryption to boot the Queue services
        if ($version < 5.3) {
            if ($version < 5.1) {
                $testKey = Str::random(16);
                $cipher = MCRYPT_RIJNDAEL_128;
            } elseif ($version < 5.2) {
                $testKey = Str::random(16);
                $cipher = 'AES-128-CBC';
            } else {
                $testKey = 'base64:' . base64_encode(random_bytes(16));
                $cipher = 'AES-128-CBC';
            }

            if (static::isLumen()) {
                $app->configure('app');
            } else {
                $app->register(new EncryptionServiceProvider($app));
            }

            $app->config->set('app.key', $testKey);
            $app->config->set('app.cipher', $cipher);
        }
    }
}
