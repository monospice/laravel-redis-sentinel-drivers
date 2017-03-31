<?php

namespace Monospice\LaravelRedisSentinel;

use Illuminate\Support\Facades\Facade;

/**
 * A Laravel facade that provides access to the RedisSentinelManager instance
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     http://github.com/monospice/laravel-redis-sentinel-drivers
 */
class RedisSentinel extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'redis-sentinel';
    }
}
