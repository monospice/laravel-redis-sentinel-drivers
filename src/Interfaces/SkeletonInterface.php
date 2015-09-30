<?php

namespace Monospice\Skeleton\Interfaces;

/**
 * An example interface demonstrating some code style guidelines
 *
 * @category Package
 * @package  Monospice\Skeleton\Interfaces
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE
 * @link     https://github.com/monospice/skeleton-project
 */
interface SkeletonInterface
{
    /**
     * An example method
     *
     * @param string $audience The audience to greet
     *
     * @return string The example audience
     */
    public function hello($audience = 'world');
}
