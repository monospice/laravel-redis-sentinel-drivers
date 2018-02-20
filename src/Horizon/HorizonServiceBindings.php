<?php

namespace Monospice\LaravelRedisSentinel\Horizon;

use ArrayIterator;
use IteratorAggregate;
use Laravel\Horizon\ServiceBindings;

/**
 * Provides the set of Horizon services that depend on the application's Redis
 * service so we can replace the dependencies with the package's Redis Sentinel
 * connection manager if needed.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     https://github.com/monospice/laravel-redis-sentinel-drivers
 */
class HorizonServiceBindings implements IteratorAggregate
{
    // Conveniently, Horizon provides a trait that declares each of the services
    // that it registers with the application container. As long as this trait
    // exists, we can use it to find the services that need the Sentinel manager
    // dependency injected.
    //
    // This trait adds the public $serviceBindings property to the service
    // provider. We could access this property from the formal Horizon service
    // provider, but we'll re-declare it here in case the package's service
    // provider loads before Horizon's provider.
    use ServiceBindings;

    /**
     * Get an iterator for the service bindings so we can iterate over an
     * instance of this class.
     *
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->serviceBindings);
    }
}
