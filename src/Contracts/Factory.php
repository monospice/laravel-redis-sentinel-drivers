<?php

namespace Monospice\LaravelRedisSentinel\Contracts;

use Illuminate\Contracts\Redis\Factory as RedisFactoryContract;

/**
 * Executes Redis commands using Sentinel connections.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     https://github.com/monospice/laravel-redis-sentinel-drivers
 */
interface Factory extends RedisFactoryContract
{
}
