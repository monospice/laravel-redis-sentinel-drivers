<?php

namespace Monospice\Skeleton\Tests\Spec;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class SkeletonSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(
            'Monospice\Skeleton\Interfaces\SkeletonInterface'
        );
    }

    function it_greets_an_audience()
    {
        $this->hello()->shouldEqual('Hello, world!');
    }
}
