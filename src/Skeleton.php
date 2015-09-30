<?php

namespace Monospice\Skeleton;

use Monospice\Skeleton\Interfaces\SkeletonInterface;

/**
 * An example class demonstrating some code style guidelines
 *
 * @category Package
 * @package  Monospice\Skeleton
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE
 * @link     https://github.com/monospice/skeleton-project
 */
class Skeleton implements SkeletonInterface
{
    // Inherit Doc from Interfaces\SkeletonInterface
    public function hello($audience = 'world')
    {
        return 'Hello, ' . $audience . '!';
    }
}
