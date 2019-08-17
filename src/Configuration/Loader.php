<?php

namespace Monospice\LaravelRedisSentinel\Configuration;

use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\Arr;
use Laravel\Horizon\Horizon;
use Laravel\Lumen\Application as LumenApplication;
use Monospice\LaravelRedisSentinel\Configuration\HostNormalizer;
use Monospice\LaravelRedisSentinel\Manager;
use UnexpectedValueException;

/**
 * The internal configuration loader for the package. Used by the package's
 * service provider.
 *
 * This package provides developers three ways to configure it: through the
 * environment, by adding config values to the configuration for the other
 * components that the package wraps, and by creating an external package
 * configuration file that overrides the default internal configuration.
 * The package uses its configuration information to set Redis connection,
 * cache, session, and queue configuration values when these are missing.
 * This approach simplifies the code needed to configure the package for many
 * applications while still providing the flexibility needed for advanced
 * setups. This class reconciles each of the configuration methods.
 *
 * The package's configuration contains partial elements from several other
 * component configurations. By default, the package removes its configuration
 * after merging the values into each of the appropriate config locations for
 * the components it initializes. This behavior prevents the artisan CLI's
 * "config:cache" command from saving unnecessary configuration values to the
 * configuration cache file. Set the value of "redis-sentinel.clean_config" to
 * FALSE to disable this behavior.
 *
 * To support these configuration scenarios, this class follows these rules:
 *
 *   - Values in application config files ("config/database.php", etc.) have
 *     the greatest precedence. The package will use these values before any
 *     others and will not modify these values if they exist.
 *   - The package will use values in a developer-supplied package config file
 *     located in the application's "config/" directory with the filename of
 *     "redis-sentinel.php" for any values not found in the application's
 *     standard configuration files before using it's default configuration.
 *   - For any configuration values not provided by standard application
 *     config files or a developer-supplied custom config file, the package
 *     uses it's internal default configuration that reads configuration values
 *     from environment variables.
 *   - The package will copy values from it's configuration to the standard
 *     application configuration at runtime if these are missing. For example,
 *     if the application configuration doesn't contain a key for "database.
 *     redis-sentinel" (the Redis Sentinel connections), this class will copy
 *     its values from "redis-sentinel.database.redis-sentinel" to "database.
 *     redis-sentinel".
 *   - After loading its configuration, the package must only use configuration
 *     values from the standard application config locations. For example, the
 *     package will read the values from "database.redis-sentinel" to configure
 *     Redis Sentinel connections, not "redis-sentinel.database.redis-sentinel".
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     https://github.com/monospice/laravel-redis-sentinel-drivers
 */
class Loader
{
    /**
     * The path to the package's default configuration file.
     *
     * @var string
     */
    const CONFIG_PATH = __DIR__ . '/../../config/redis-sentinel.php';

    /**
     * Flag that indicates whether the package should check that the framework
     * runs full Laravel before loading Horizon support.
     *
     * Horizon does not yet officially support Lumen applications, so the
     * package will not configure itself for Horizon in Lumen applications by
     * default. Set this value to TRUE in bootstrap/app.php to short-circuit
     * the check and attempt to load Horizon support anyway. This provides for
     * testing unofficial Lumen implementations. Use at your own risk.
     *
     * @var bool
     */
    public static $ignoreHorizonRequirements = false;

    /**
     * Indicates whether the current application runs the Lumen framework.
     *
     * @var bool
     */
    public $isLumen;

    /**
     * Indicates whether the current application supports sessions.
     *
     * @var bool
     */
    public $supportsSessions;

    /**
     * Indicates whether the package should override Laravel's standard Redis
     * API ("Redis" facade and "redis" service binding).
     *
     * @var bool
     */
    public $shouldOverrideLaravelRedisApi;

    /**
     * Indicates whether Laravel Horizon is installed. Currently FALSE in Lumen.
     *
     * @var bool
     */
    public $horizonAvailable;

    /**
     * Indicates whether the package should integrate with Laravel Horizon
     * based on availability and the value of the "horizon.driver" directive.
     *
     * @var bool
     */
    public $shouldIntegrateHorizon;

    /**
     * The current Laravel or Lumen application instance that provides context
     * and services used to load the appropriate configuration.
     *
     * @var LaravelApplication|LumenApplication
     */
    private $app;

    /**
     * Used to fetch and set application configuration values.
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    private $config;

    /**
     * Contains the set of configuration values used to configure the package
     * as loaded from "config/redis-sentinel.php". Empty when the application's
     * standard config files provide all the values needed to configure the
     * package (such as when a developer provides a custom config).
     *
     * @var array
     */
    private $packageConfig;

    /**
     * Initialize the configuration loader. Any actual loading occurs when
     * calling the 'loadConfiguration()' method.
     *
     * @param LaravelApplication|LumenApplication $app The current application
     * instance that provides context and services needed to load the
     * appropriate configuration.
     */
    public function __construct($app)
    {
        $this->app = $app;
        $this->config = $app->make('config');

        $lumenApplicationClass = 'Laravel\Lumen\Application';

        $this->isLumen = $app instanceof $lumenApplicationClass;
        $this->supportsSessions = $app->bound('session');
        $this->horizonAvailable = static::$ignoreHorizonRequirements
            || ! $this->isLumen && class_exists(Horizon::class);
    }

    /**
     * Create an instance of the loader and load the configuration in one step.
     *
     * @param LaravelApplication|LumenApplication $app The current application
     * instance that provides context and services needed to load the
     * appropriate configuration.
     *
     * @return self An initialized instance of this class
     */
    public static function load($app)
    {
        $loader = new self($app);
        $loader->loadConfiguration();

        return $loader;
    }

    /**
     * Load the package configuration.
     *
     * @return void
     */
    public function loadConfiguration()
    {
        if ($this->shouldLoadConfiguration()) {
            $this->loadPackageConfiguration();
        }

        // Previous versions of the package looked for the value 'sentinel':
        $redisDriver = $this->config->get('database.redis.driver');
        $this->shouldOverrideLaravelRedisApi = $redisDriver === 'redis-sentinel'
            || $redisDriver === 'sentinel';

        $this->shouldIntegrateHorizon = $this->horizonAvailable
            && $this->config->get('horizon.driver') === 'redis-sentinel';
    }

    /**
     * Sets the Horizon Redis Sentinel connection configuration.
     *
     * @return void
     */
    public function loadHorizonConfiguration()
    {
        // We set the config value "redis-sentinel.load_horizon" to FALSE after
        // configuring Horizon connections to skip this step after caching the
        // application configuration via "artisan config:cache":
        if ($this->config->get('redis-sentinel.load_horizon', true) !== true) {
            return;
        }

        $horizonConfig = $this->getSelectedHorizonConnectionConfiguration();
        $options = Arr::get($horizonConfig, 'options', [ ]);
        $options['prefix'] = $this->config->get('horizon.prefix', 'horizon:');

        $horizonConfig['options'] = $options;

        $this->config->set('database.redis-sentinel.horizon', $horizonConfig);
        $this->config->set('redis-sentinel.load_horizon', false);
    }

    /**
     * Get the version number of the current Laravel or Lumen application.
     *
     * @return string The version as declared by the framework.
     */
    public function getApplicationVersion()
    {
        if ($this->isLumen) {
            return substr($this->app->version(), 7, 3); // ex. "5.4"
        }

        return \Illuminate\Foundation\Application::VERSION;
    }

    /**
     * Fetch the specified application configuration value.
     *
     * This helper method enables the package's service providers to get config
     * values without having to resolve the config service from the container.
     *
     * @param string|array $key     The key(s) for the value(s) to fetch.
     * @param mixed        $default Returned if the key does not exist.
     *
     * @return mixed The requested configuration value or the provided default
     * if the key does not exist.
     */
    public function get($key, $default = null)
    {
        return $this->config->get($key, $default);
    }

    /**
     * Set the specified application configuration value.
     *
     * This helper method enables the package's service providers to set config
     * values without having to resolve the config service from the container.
     *
     * @param string|array $key   The key of the value or a tree of values as
     * an associative array.
     * @param mixed        $value The value to set for the specified key.
     *
     * @return void
     */
    public function set($key, $value = null)
    {
        $this->config->set($key, $value);
    }

    /**
     * Determine whether the package service provider should immediately boot
     * the package services following the registration phase.
     *
     * Some third-party packages contain service providers that don't follow
     * Laravel's boostrapping convention. These may reference this package's
     * Sentinel drivers (cache, session, etc.) during the registration phase
     * before this service provider finishes binding those in the boot phase
     * afterward. An application can overcome the issue by setting the value
     * of "redis-sentinel.auto_boot" to TRUE so that this provider boots the
     * package's drivers during the registration phase.
     *
     * @return bool TRUE if the package is explicitly configured to auto-boot.
     */
    public function shouldAutoBoot()
    {
        return $this->config->get('redis-sentinel.auto_boot', false);
    }

    /**
     * Determine if the package should automatically configure itself.
     *
     * Developers may set the value of "redis-sentinel.load_config" to FALSE to
     * disable the package's automatic configuration. This class also sets this
     * value to FALSE after loading the package configuration to skip the auto-
     * configuration when the application cached its configuration values (via
     * "artisan config:cache", for example).
     *
     * @return bool TRUE if the package should load its configuration
     */
    protected function shouldLoadConfiguration()
    {
        if ($this->isLumen) {
            $this->app->configure('redis-sentinel');
        }

        return $this->config->get('redis-sentinel.load_config', true) === true;
    }

    /**
     * Configure the Lumen components that this package depends on.
     *
     * Lumen lazily loads many of its components. We must instruct Lumen to
     * load the configuration for components that this class configures so
     * that the values are accessible and so that the framework does not
     * revert the configuration settings that this class changes when one of
     * the components initializes later.
     *
     * @return void
     */
    protected function configureLumenComponents()
    {
        $this->app->configure('database');
        $this->app->configure('broadcasting');
        $this->app->configure('cache');
        $this->app->configure('queue');
    }

    /**
     * Copy the Redis Sentinel connection configuration to use for Horizon
     * connections from the connection specified by "horizon.use".
     *
     * @return array The configuration matching the connection name specified
     * by the "horizon.use" config directive.
     *
     * @throws UnexpectedValueException If no Redis Sentinel connection matches
     * the name declared by "horizon.use".
     */
    protected function getSelectedHorizonConnectionConfiguration()
    {
        $use = $this->config->get('horizon.use', 'default');
        $connectionConfig = $this->config->get("database.redis-sentinel.$use");

        if ($connectionConfig === null) {
            throw new UnexpectedValueException(
                "The Horizon Redis Sentinel connection [$use] is not defined."
            );
        }

        return $connectionConfig;
    }

    /**
     * Reconcile the package configuration and use it to set the appropriate
     * configuration values for other application components.
     *
     * @return void
     */
    protected function loadPackageConfiguration()
    {
        if ($this->isLumen) {
            $this->configureLumenComponents();
        }

        $this->setConfigurationFor('database.redis-sentinel');
        $this->setConfigurationFor('database.redis.driver');
        $this->setConfigurationFor('broadcasting.connections.redis-sentinel');
        $this->setConfigurationFor('cache.stores.redis-sentinel');
        $this->setConfigurationFor('queue.connections.redis-sentinel');
        $this->setSessionConfiguration();

        $this->normalizeHosts();

        $this->cleanPackageConfiguration();
    }

    /**
     * Set the application configuration value for the specified key with the
     * value from the package configuration.
     *
     * @param string $configKey   The key of the config value to set. Should
     * correspond to a key in the package's configuration.
     * @param bool   $checkExists If TRUE, don't set the value if the key
     * already exists in the application configuration.
     *
     * @return void
     */
    protected function setConfigurationFor($configKey, $checkExists = true)
    {
        if ($checkExists && $this->config->has($configKey)) {
            return;
        }

        $config = $this->getPackageConfigurationFor($configKey);

        $this->config->set($configKey, $config);
    }

    /**
     * Set the application session configuration as specified by the package's
     * configuration if the app supports sessions.
     *
     * @return void
     */
    protected function setSessionConfiguration()
    {
        if (! $this->supportsSessions
            || $this->config->get('session.driver') !== 'redis-sentinel'
            || $this->config->get('session.connection') !== null
        ) {
            return;
        }

        $this->setConfigurationFor('session.connection', false);
    }

    /**
     * Get the package configuration for the specified key.
     *
     * @param string $configKey The key of the configuration value to get
     *
     * @return mixed The value of the configuration with the specified key
     */
    protected function getPackageConfigurationFor($configKey)
    {
        if ($this->packageConfig === null) {
            $this->mergePackageConfiguration();
        }

        return Arr::get($this->packageConfig, $configKey);
    }

    /**
     * Merge the package's default configuration with the override config file
     * supplied by the developer, if any.
     *
     * @return void
     */
    protected function mergePackageConfiguration()
    {
        $defaultConfig = require self::CONFIG_PATH;
        $currentConfig = $this->config->get('redis-sentinel', [ ]);

        $this->packageConfig = array_merge($defaultConfig, $currentConfig);
    }

    /**
     * Parse Redis Sentinel connection host definitions to create single host
     * entries for host definitions that specify multiple hosts.
     *
     * @return void
     */
    protected function normalizeHosts()
    {
        $connections = $this->config->get('database.redis-sentinel');

        if (! is_array($connections)) {
            return;
        }

        $this->config->set(
            'database.redis-sentinel',
            HostNormalizer::normalizeConnections($connections)
        );
    }

    /**
     * Remove the package's configuration from the application configuration
     * repository.
     *
     * This package's configuration contains partial elements from several
     * other component configurations. By default, the package removes its
     * configuration after merging the values into each of the appropriate
     * config locations for the components it initializes. This behavior
     * prevents the artisan "config:cache" command from saving unnecessary
     * configuration values to the cache file.
     *
     * @return void
     */
    protected function cleanPackageConfiguration()
    {
        // When we're finished with the internal package configuration, break
        // the reference so that it can be garbage-collected:
        $this->packageConfig = null;

        if ($this->config->get('redis-sentinel.clean_config', true) === true) {
            $this->config->set('redis-sentinel', [
                'auto_boot' => $this->shouldAutoBoot(),
                'Config merged. Set redis-sentinel.clean_config=false to keep.',
            ]);
        }

        // Skip loading package config when cached:
        $this->config->set('redis-sentinel.load_config', false);
    }
}
