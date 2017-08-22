<?php

namespace Monospice\LaravelRedisSentinel\Configuration;

use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\Arr;
use Laravel\Lumen\Application as LumenApplication;
use Monospice\LaravelRedisSentinel\Configuration\HostNormalizer;

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
        if (! $this->shouldLoadConfiguration()) {
            return;
        }

        if ($this->isLumen) {
            $this->configureLumenComponents();
        }

        $this->loadPackageConfiguration();
    }

    /**
     * Determine whether the package should override Laravel's standard Redis
     * API ("Redis" facade and "redis" service binding).
     *
     * @return bool TRUE if the package should override Laravel's standard
     * Redis API
     */
    public function shouldOverrideLaravelRedisApi()
    {
        $redisDriver = $this->config->get('database.redis.driver');

        // Previous versions of the package looked for the value 'sentinel':
        return $redisDriver === 'redis-sentinel' || $redisDriver === 'sentinel';
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
        $this->app->configure('cache');
        $this->app->configure('queue');
    }

    /**
     * Reconcile the package configuration and use it to set the appropriate
     * configuration values for other application components.
     *
     * @return void
     */
    protected function loadPackageConfiguration()
    {
        $this->setConfigurationFor('database.redis-sentinel');
        $this->setConfigurationFor('database.redis.driver');
        $this->setConfigurationFor('cache.stores.redis-sentinel');
        $this->setConfigurationFor('queue.connections.redis-sentinel');
        $this->setSessionConfiguration();

        $this->normalizeHosts();

        if ($this->packageConfig !== null) {
            $this->cleanPackageConfiguration();
        }
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
        if ($this->config->get('redis-sentinel.clean_config', true) !== true) {
            return;
        }

        $this->config->set('redis-sentinel', [
            'Config merged. Set "redis-sentinel.clean_config" = false to keep.',
            'load_config' => false, // skip loading package config when cached
        ]);
    }
}
