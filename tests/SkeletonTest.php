<?php

namespace Monospice\Skeleton\Tests;

use Monospice\Skeleton\Skeleton;

/**
 * An example test. Remove this file.
 *
 * @author Cy Rossignol <cy@rossignols.me>
 */
class SkeletonTest extends \PHPUnit_Framework_TestCase
{
    public function testSkeleton()
    {
        $skeleton = new Skeleton();
        $audience = 'world';

        $this->assertSame('Hello, world!', $skeleton->hello());
    }
}
