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

        $command = [ (new PhpExecutableFinder())->find(), 'artisan', $command ];
        $laravelVersion = ApplicationFactory::getApplicationVersion();

        // Symfony removed support for string commands in newer versions. When
        // running Laravel >= 7, pass the command as an array. Otherwise, send
        // it as a string:
        //
        if (version_compare($laravelVersion, '7.0', 'lt')) {
            $command = implode(' ', $command);
        }

        parent::__construct($command, ApplicationFactory::APP_PATH);
    }
}
