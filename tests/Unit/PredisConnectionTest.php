<?php

namespace Monospice\LaravelRedisSentinel\Tests\Unit;

use Monospice\LaravelRedisSentinel\PredisConnection;
use Monospice\SpicyIdentifiers\DynamicMethod;
use PHPUnit_Framework_TestCase as TestCase;

class PredisConnectionTest extends TestCase
{
    /**
     * Sentinel-specific connection options and the values to test for.
     *
     * We set non-default values here so we can verify that the subject consumes
     * the new settings.
     *
     * @var array
     */
    protected static $sentinelOptions = [
        'sentinel_timeout' => 0.99,
        'retry_limit' => 99,
        'retry_wait' => 9999,
        'update_sentinels' => true,
    ];

    /**
     * The instance of the Predis client wrapper under test.
     *
     * @var PredisConnection
     */
    protected $subject;

    /**
     * Run this setup before each test.
     *
     * @return void
     */
    public function setUp()
    {
        $config = require __DIR__ . '/../stubs/config.php';

        $this->subject = new PredisConnection(
            $config['database']['redis-sentinel']['default'],
            array_merge($config['database']['redis-sentinel']['options'], [
                'replication' => 'sentinel'
            ])
        );
    }

    public function testIsInitializable()
    {
        $class = 'Monospice\LaravelRedisSentinel\PredisConnection';

        $this->assertInstanceOf($class, $this->subject);
    }

    public function testIsAPredisClient()
    {
        $interface = 'Predis\ClientInterface';

        $this->assertInstanceOf($interface, $this->subject);
    }

    public function testSetsSentinelOptionsFluentlyThroughApi()
    {
        $connection = $this->subject->getConnection();

        foreach (static::$sentinelOptions as $option => $value) {
            $method = DynamicMethod::parseFromUnderscore($option);
            $property = $method->name();

            $returnValue = $method->prepend('set')
                ->callOn($this->subject, [ $value ]);

            $this->assertSame($this->subject, $returnValue);

            // These classes provide no public interface to detect these values
            $this->assertAttributeEquals($value, $property, $connection);
        }

        $this->assertAttributeEquals(99, 'retryLimit', $this->subject);
        $this->assertAttributeEquals(9999, 'retryWait', $this->subject);
    }
}
