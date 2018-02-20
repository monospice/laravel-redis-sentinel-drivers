<?php

/**
 * Executes Artisan console commands for integration testing.
 *
 * Tests use this template to create an Artisan executable that functions
 * similarly to the Artisan CLI that ships with Laravel. The template expects
 * that a test will fill the placeholder with the Redis Sentinel connection
 * configuration for the test environment.
 *
 * Currently, this script bootstraps a minimal application that only provides
 * services for testing Horizon compatibility.
 *
 * @see Monospice\LaravelRedisSentinel\Tests\Support\ArtisanProcess
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider;
use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

$app = ApplicationFactory::makeForConsole();
$app->config->set(require(__DIR__ . '/../../tests/stubs/config.php'));
$app->config->set('cache.default', 'redis-sentinel'); // for "queue:work" cmd

// The test will replace this value with the Sentinel connection configuration:
$app->config->set('database.redis-sentinel', %s);

ApplicationFactory::configureHorizonComponents($app);

$app->register(RedisSentinelServiceProvider::class);
$app->boot();

$kernel = $app->make(Kernel::class);
$input = new ArgvInput();
$exitCode = $kernel->handle($input, new ConsoleOutput());

$kernel->terminate($input, $exitCode);

exit($exitCode);
