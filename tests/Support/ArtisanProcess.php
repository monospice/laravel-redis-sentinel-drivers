<?php

namespace Monospice\LaravelRedisSentinel\Tests\Support;

use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs the specified Artisan console command in a separate process.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     https://github.com/monospice/laravel-redis-sentinel-drivers
 */
class ArtisanProcess extends Process
{
    /**
     * Generate an Artisan executable using the provided configuration and
     * create a process object that executes the specified Artisan command
     * with that file.
     *
     * @param array  $connectionConfig Redis Sentinel connection configuration.
     * @param string $command          The Artisan command to execute.
     */
    public function __construct(array $connectionConfig, $command)
    {
        file_put_contents(ApplicationFactory::APP_PATH . '/artisan', sprintf(
            file_get_contents(__DIR__ . '/../stubs/artisan.php'),
            var_export($connectionConfig, true)
        ));

        parent::__construct(
            (new PhpExecutableFinder())->find() . ' artisan ' . $command,
            ApplicationFactory::APP_PATH
        );
    }
}
