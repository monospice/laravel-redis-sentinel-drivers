<?php

namespace Monospice\LaravelRedisSentinel\Tests\Unit\Configuration;

use Monospice\LaravelRedisSentinel\Configuration\Loader;
use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
use PHPUnit_Framework_TestCase as TestCase;
use UnexpectedValueException;

class LoaderTest extends TestCase
{
    /**
     * An instance of the Laravel or Lumen application container.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $app;

    /**
     * A reference to the current application config repository for convenience.
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * The instance of the package's configuration loader under test.
     *
     * @var RedisSentinelServiceProvider
     */
    protected $loader;

    /**
     * The keys of the application configuration values set up by the package
     * when using automatic configuration.
     *
     * @var array
     */
    protected $configKeys = [
        'broadcast_connection' => 'broadcasting.connections.redis-sentinel',
        'cache_store' => 'cache.stores.redis-sentinel',
        'sentinel_connections' => 'database.redis-sentinel',
        'redis_driver' => 'database.redis.driver',
        'queue_connector' => 'queue.connections.redis-sentinel',
        'session_connection' => 'session.connection',
    ];

    /**
     * The set of environment variables consumed by this package mapped to the
     * configuration keys that each environment variable can set.
     *
     * @var array
     */
    protected $defaultEnvVars = [
        'REDIS_HOST' => [
            'database.redis-sentinel.default.0.host',
            'database.redis-sentinel.cache.0.host',
            'database.redis-sentinel.session.0.host',
            'database.redis-sentinel.queue.0.host',
        ],
        'REDIS_SENTINEL_HOST' => [
            'database.redis-sentinel.default.0.host',
            'database.redis-sentinel.cache.0.host',
            'database.redis-sentinel.session.0.host',
            'database.redis-sentinel.queue.0.host',
        ],
        'REDIS_PORT' => [
            'database.redis-sentinel.default.0.port',
            'database.redis-sentinel.cache.0.port',
            'database.redis-sentinel.session.0.port',
            'database.redis-sentinel.queue.0.port',
        ],
        'REDIS_SENTINEL_PORT' => [
            'database.redis-sentinel.default.0.port',
            'database.redis-sentinel.cache.0.port',
            'database.redis-sentinel.session.0.port',
            'database.redis-sentinel.queue.0.port',
        ],
        'REDIS_PASSWORD' => [
            'database.redis-sentinel.options.parameters.password',
            'database.redis-sentinel.cache.options.parameters.password',
            'database.redis-sentinel.session.options.parameters.password',
            'database.redis-sentinel.queue.options.parameters.password',
        ],
        'REDIS_SENTINEL_PASSWORD' => [
            'database.redis-sentinel.options.parameters.password',
            'database.redis-sentinel.cache.options.parameters.password',
            'database.redis-sentinel.session.options.parameters.password',
            'database.redis-sentinel.queue.options.parameters.password',
        ],
        'REDIS_DATABASE' => [
            'database.redis-sentinel.options.parameters.database',
            'database.redis-sentinel.cache.options.parameters.database',
            'database.redis-sentinel.session.options.parameters.database',
            'database.redis-sentinel.queue.options.parameters.database',
        ],
        'REDIS_SENTINEL_DATABASE' => [
            'database.redis-sentinel.options.parameters.database',
            'database.redis-sentinel.cache.options.parameters.database',
            'database.redis-sentinel.session.options.parameters.database',
            'database.redis-sentinel.queue.options.parameters.database',
        ],
        'REDIS_SENTINEL_SERVICE' => [
            'database.redis-sentinel.options.service',
            'database.redis-sentinel.cache.options.service',
            'database.redis-sentinel.session.options.service',
            'database.redis-sentinel.queue.options.service',
        ],
        'REDIS_SENTINEL_TIMEOUT' => [
            'database.redis-sentinel.options.sentinel_timeout',
        ],
        'REDIS_SENTINEL_RETRY_LIMIT' => [
            'database.redis-sentinel.options.retry_limit',
        ],
        'REDIS_SENTINEL_RETRY_WAIT' => [
            'database.redis-sentinel.options.retry_wait',
        ],
        'REDIS_SENTINEL_DISCOVERY' => [
            'database.redis-sentinel.options.update_sentinels',
        ],
        'REDIS_BROADCAST_HOST' => [
            'database.redis-sentinel.broadcasting.0.host',
        ],
        'REDIS_BROADCAST_PORT' => [
            'database.redis-sentinel.broadcasting.0.port',
        ],
        'REDIS_BROADCAST_SERVICE' => [
            'database.redis-sentinel.broadcasting.options.service',
        ],
        'REDIS_BROADCAST_PASSWORD' => [
            'database.redis-sentinel.broadcasting.options.parameters.password',
        ],
        'REDIS_BROADCAST_DATABASE' => [
            'database.redis-sentinel.broadcasting.options.parameters.database',
        ],
        'REDIS_CACHE_HOST' => [
            'database.redis-sentinel.cache.0.host',
        ],
        'REDIS_CACHE_PORT' => [
            'database.redis-sentinel.cache.0.port',
        ],
        'REDIS_CACHE_SERVICE' => [
            'database.redis-sentinel.cache.options.service',
        ],
        'REDIS_CACHE_PASSWORD' => [
            'database.redis-sentinel.cache.options.parameters.password',
        ],
        'REDIS_CACHE_DATABASE' => [
            'database.redis-sentinel.cache.options.parameters.database',
        ],
        'REDIS_SESSION_HOST' => [
            'database.redis-sentinel.session.0.host',
        ],
        'REDIS_SESSION_PORT' => [
            'database.redis-sentinel.session.0.port',
        ],
        'REDIS_SESSION_SERVICE' => [
            'database.redis-sentinel.session.options.service',
        ],
        'REDIS_SESSION_PASSWORD' => [
            'database.redis-sentinel.session.options.parameters.password',
        ],
        'REDIS_SESSION_DATABASE' => [
            'database.redis-sentinel.session.options.parameters.database',
        ],
        'REDIS_QUEUE_HOST' => [
            'database.redis-sentinel.queue.0.host',
        ],
        'REDIS_QUEUE_PORT' => [
            'database.redis-sentinel.queue.0.port',
        ],
        'REDIS_QUEUE_SERVICE' => [
            'database.redis-sentinel.queue.options.service',
        ],
        'REDIS_QUEUE_PASSWORD' => [
            'database.redis-sentinel.queue.options.parameters.password',
        ],
        'REDIS_QUEUE_DATABASE' => [
            'database.redis-sentinel.queue.options.parameters.database',
        ],
        'REDIS_DRIVER' => [
            'database.redis.driver',
        ],
        'BROADCAST_REDIS_CONNECTION' => [
            'broadcasting.connections.redis-sentinel.connection',
        ],
        'BROADCAST_REDIS_SENTINEL_CONNECTION' => [
            'broadcasting.connections.redis-sentinel.connection',
        ],
        'CACHE_REDIS_CONNECTION' => [
            'cache.stores.redis-sentinel.connection',
        ],
        'CACHE_REDIS_SENTINEL_CONNECTION' => [
            'cache.stores.redis-sentinel.connection',
        ],
        'SESSION_CONNECTION' => [
            'session.connection',
        ],
        'QUEUE_REDIS_CONNECTION' => [
            'queue.connections.redis-sentinel.connection',
        ],
        'QUEUE_REDIS_SENTINEL_CONNECTION' => [
            'queue.connections.redis-sentinel.connection',
        ],
    ];

    /**
     * Run this setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        $this->startTestWithBareApplication();

        if (ApplicationFactory::isLumen()) {
            unset($this->configKeys['session_connection']);
            unset($this->defaultEnvVars['SESSION_CONNECTION']);
        }
    }

    public function testIsInitializable()
    {
        $this->assertInstanceOf(Loader::class, $this->loader);
    }

    public function testIsInitializableWithFactoryMethod()
    {
        // Don't actually load anything when calling the factory method for
        // this test:
        $this->config->set('redis-sentinel.load_config', false);

        $this->assertInstanceOf(Loader::class, Loader::load($this->app));
    }

    public function testChecksWhetherApplicationIsLumen()
    {
        if (ApplicationFactory::isLumen()) {
            $this->assertTrue($this->loader->isLumen);
        } else {
            $this->assertFalse($this->loader->isLumen);
        }
    }

    public function testChecksWhetherApplicationSupportsSessions()
    {
        if (ApplicationFactory::isLumen()) {
            $this->assertFalse($this->loader->supportsSessions);
        } else {
            $this->assertTrue($this->loader->supportsSessions);
        }
    }

    public function testChecksWhetherPackageShouldOverrideRedisApi()
    {
        $this->startTestWithConfiguredApplication();
        $this->config->set('database.redis.driver', 'redis-sentinel');
        $this->loader->loadConfiguration();
        $this->assertTrue($this->loader->shouldOverrideLaravelRedisApi);

        // Previous versions of the package looked for the value 'sentinel':
        $this->startTestWithConfiguredApplication();
        $this->config->set('database.redis.driver', 'sentinel');
        $this->loader->loadConfiguration();
        $this->assertTrue($this->loader->shouldOverrideLaravelRedisApi);

        $this->startTestWithConfiguredApplication();
        $this->config->set('database.redis.driver', 'default');
        $this->loader->loadConfiguration();
        $this->assertFalse($this->loader->shouldOverrideLaravelRedisApi);
    }

    public function testChecksWhetherApplicationSupportsHorizon()
    {
        if (ApplicationFactory::isHorizonAvailable()
            && ! ApplicationFactory::isLumen()
        ) {
            $this->assertTrue($this->loader->horizonAvailable);
        } else {
            $this->assertFalse($this->loader->horizonAvailable);
        }
    }

    public function testChecksWhetherPackageShouldIntegrateHorizon()
    {
        if (! ApplicationFactory::isHorizonAvailable()
            || ApplicationFactory::isLumen()
        ) {
            $this->loader->loadConfiguration();
            $this->assertFalse($this->loader->shouldIntegrateHorizon);

            return;
        }

        $this->config->set('horizon.driver', 'redis-sentinel');
        $this->loader->loadConfiguration();
        $this->assertTrue($this->loader->shouldIntegrateHorizon);

        $this->config->set('horizon.driver', 'default');
        $this->loader->loadConfiguration();
        $this->assertFalse($this->loader->shouldIntegrateHorizon);
    }

    public function testChecksWtherPackageShouldAutoBoot()
    {
        $this->assertFalse($this->loader->shouldAutoBoot());

        $this->config->set('redis-sentinel.auto_boot', true);
        $this->assertTrue($this->loader->shouldAutoBoot());

        $this->config->set('redis-sentinel.auto_boot', false);
        $this->assertFalse($this->loader->shouldAutoBoot());
    }

    public function testDetectsApplicationVersion()
    {
        $expected = ApplicationFactory::getApplicationVersion();
        $actual = $this->loader->getApplicationVersion();

        $this->assertEquals($expected, $actual);
    }

    public function testGetsAnApplicationConfigValue()
    {
        $this->config->set('test-key', 'test value');

        $this->assertEquals('test value', $this->loader->get('test-key'));
    }

    public function testSetsAnApplicationConfigValue()
    {
        $this->loader->set('test-key', 'test value');

        $this->assertEquals('test value', $this->config->get('test-key'));
    }

    public function testLoadsLumenConfigurationDependencies()
    {
        if (ApplicationFactory::isLumen()) {
            $this->loader->loadConfiguration();

            $this->assertTrue($this->config->has('database'));
            $this->assertTrue($this->config->has('broadcasting'));
            $this->assertTrue($this->config->has('cache'));
            $this->assertTrue($this->config->has('queue'));
        }
    }

    public function testLoadsDefaultConfiguration()
    {
        // The package only sets "session.connection" when "session.driver"
        // equals "redis-sentinel"
        if (! ApplicationFactory::isLumen()) {
            $this->config->set('session.driver', 'redis-sentinel');
        }

        $this->loader->loadConfiguration();

        foreach ($this->configKeys as $configKey) {
            $this->assertTrue($this->config->has($configKey), $configKey);
        }
    }

    public function testLoadsDefaultConfigurationFromEnvironment()
    {
        $expected = 'environment variable value';

        foreach ($this->defaultEnvVars as $env => $configKeys) {
            putenv("$env=$expected"); // Set the environment variable

            // Reset the application configuration for each enviroment variable
            // because several of the variables set the same config key:
            $this->startTestWithBareApplication();

            // The package only sets "session.connection" when "session.driver"
            // equals "redis-sentinel"
            if (! ApplicationFactory::isLumen()) {
                $this->config->set('session.driver', 'redis-sentinel');
            }

            $this->loader->loadConfiguration();

            foreach ($configKeys as $configKey) {
                $this->assertEquals(
                    $expected,
                    $this->config->get($configKey),
                    "$env -> $configKey"
                );
            }

            putenv($env); // Unset the environment variable
        }
    }

    public function testSkipsLoadingDefaultConfigurationIfDisabled()
    {
        $this->config->set('redis-sentinel.load_config', false);
        $this->loader->loadConfiguration();

        foreach ($this->configKeys as $configKey) {
            $this->assertFalse($this->config->has($configKey), $configKey);
        }
    }

    public function testSkipsLoadingDefaultConfigurationIfProvidedByApp()
    {
        $this->startTestWithConfiguredApplication();

        $expected = 'provided by app config file';

        foreach ($this->configKeys as $configKey) {
            $this->config->set($configKey, $expected);
        }

        $this->loader->loadConfiguration();

        foreach ($this->configKeys as $configKey) {
            $this->assertEquals(
                $expected,
                $this->config->get($configKey),
                $configKey
            );
        }

        // The loader should still set this value to FALSE to avoid reloading
        // the configuration when cached:
        $this->assertFalse($this->config->get('redis-sentinel.load_config'));
    }

    public function testSetsSessionConnectionIfMissing()
    {
        if (ApplicationFactory::isLumen()) {
            return;
        }

        $expected = 'connection name';

        $this->config->set('session', [ 'driver' => 'redis-sentinel' ]);
        $this->config->set('redis-sentinel.session.connection', $expected);

        $this->loader->loadConfiguration();

        $this->assertEquals(
            $expected,
            $this->config->get('session.connection')
        );
    }

    public function testSkipsSettingSessionConnectionIfExists()
    {
        if (ApplicationFactory::isLumen()) {
            return;
        }

        $expected = 'already set';

        $this->config->set('session', [
            'driver' => 'redis-sentinel'  ,
            'connection' => $expected,
        ]);

        $this->loader->loadConfiguration();

        $this->assertEquals(
            $expected,
            $this->config->get('session.connection')
        );
    }

    public function testConfiguresHorizonConnection()
    {
        $expected = [ [ 'host' => 'sentinel', 'port' => 26379, ] ];

        $this->config->set('database.redis-sentinel.default', $expected);
        $this->config->set('horizon.prefix', 'test-prefix:');
        $expected['options']['prefix'] = 'test-prefix:';

        $this->loader->loadHorizonConfiguration();

        $horizonConfig = $this->config->get('database.redis-sentinel.horizon');
        $this->assertEquals($expected, $horizonConfig);

        // We set the config value "redis-sentinel.load_horizon" to FALSE after
        // configuring Horizon connections to skip this step after caching the
        // application configuration via "artisan config:cache":
        $this->assertFalse($this->config->get('redis-sentinel.load_horizon'));
    }

    public function testSkipsLoadingHorizonConfigurationIfCached()
    {
        $this->config->set('database.redis-sentinel.horizon', 'expected');
        $this->config->set('redis-sentinel.load_horizon', false);

        $this->loader->loadHorizonConfiguration();

        $horizonConfig = $this->config->get('database.redis-sentinel.horizon');
        $this->assertEquals('expected', $horizonConfig);
    }

    public function testDisallowsUsingNonexistantConnectionForHorizon()
    {
        $this->config->set('horizon.use', 'not-a-connection');

        $this->setExpectedException(UnexpectedValueException::class);

        $this->loader->loadHorizonConfiguration();
    }

    public function testCleansPackageConfiguration()
    {
        foreach ($this->configKeys as $configKey) {
            $packageConfigKey = "redis-sentinel.$configKey";
            $this->config->set($packageConfigKey, "dummy value");
        }

        $this->loader->loadConfiguration();

        // The configuration loader under test adds a message that indicates
        // that the package cleaned its configuration and sets the value of
        // "redis-sentinel.load_config" to FALSE to prevent the package from
        // reloading its configuration when cached ("artisan config:cache").
        $this->assertFalse($this->config->get('redis-sentinel.load_config'));

        foreach ($this->configKeys as $configKey) {
            $packageConfigKey = "redis-sentinel.$configKey";
            $this->assertFalse($this->config->has($packageConfigKey));
        }
    }

    public function testSkipsCleaningPackageConfigurationWhenDisabled()
    {
        $this->config->set('redis-sentinel.clean_config', false);

        foreach ($this->configKeys as $configKey) {
            $packageConfigKey = "redis-sentinel.$configKey";
            $this->config->set($packageConfigKey, "dummy value");
        }

        $this->loader->loadConfiguration();

        // The loader should still set this value to FALSE to avoid reloading
        // the configuration when cached:
        $this->assertFalse($this->config->get('redis-sentinel.load_config'));

        foreach ($this->configKeys as $configKey) {
            $packageConfigKey = "redis-sentinel.$configKey";
            $this->assertTrue($this->config->has($packageConfigKey));
        }
    }

    public function testNormalizesSentinelConnectionHosts()
    {
        $this->startTestWithConfiguredApplication();

        // To support environment-based configuration, the package allows
        // developers to provide comma-seperated Redis Sentinel host values
        // to specify multiple hosts through a single environment variable.
        $this->config->set('database.redis-sentinel', [
            'connection' => [
                [
                    // The package should split a comma-seperated string of
                    // hosts into individual host definitions. Any hosts that
                    // contain more than the hostname or IP address don't need
                    // an array. It should trim any spaces around the comma:
                    'host' => 'host1,10.0.0.1,host3:999 ,  tcp://host4:999',
                    // Hosts above that don't specify a port should inherit the
                    // port from this block:
                    'port' => 888,
                ],
                // The package should leave a single host string alone:
                'tcp://host5:999',
                // But it should split a comma-seperated string of hosts if the
                // developer uses this feature in their own package config:
                'tcp://host6:999,tcp://host7:999',
            ],
        ]);

        $this->loader->loadConfiguration();

        $this->assertEquals(
            [
                'connection' => [
                    [
                        'host' => 'host1',
                        'port' => 888,
                    ],
                    [
                        'host' => '10.0.0.1',
                        'port' => 888,
                    ],
                    'host3:999',
                    'tcp://host4:999',
                    'tcp://host5:999',
                    'tcp://host6:999',
                    'tcp://host7:999',
                ],
            ],
            $this->config->get('database.redis-sentinel')
        );
    }

    /**
     * Reset the current application, configuration, and loader under test
     * with new instances using an un-configured application.
     *
     * @return void
     */
    protected function startTestWithBareApplication()
    {
        $this->app = ApplicationFactory::make(false);
        $this->config = $this->app->config;
        $this->loader = new Loader($this->app);
    }

    /**
     * Reset the current application, configuration, and loader under test
     * with new instances using an pre-configured application.
     *
     * @return void
     */
    protected function startTestWithConfiguredApplication()
    {
        $this->app = ApplicationFactory::make();
        $this->config = $this->app->config;
        $this->loader = new Loader($this->app);
    }
}
