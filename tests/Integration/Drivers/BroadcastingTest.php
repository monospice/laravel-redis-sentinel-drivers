<?php

namespace Monospice\LaravelRedisSentinel\Tests\Integration\Drivers;

use Monospice\LaravelRedisSentinel\RedisSentinelServiceProvider;
use Monospice\LaravelRedisSentinel\Tests\Support\ApplicationFactory;
use Monospice\LaravelRedisSentinel\Tests\Support\IntegrationTestCase;

class BroadcastingTest extends IntegrationTestCase
{
    /**
     * The instance of the Redis Sentinel broadcaster under test.
     *
     * @var Illuminate\Contracts\Broadcasting\Broadcaster
     */
    protected $subject;

    /**
     * Run this setup before each test
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $app = ApplicationFactory::make();
        $app->config->set(require(__DIR__ . '/../../stubs/config.php'));
        $app->config->set('database.redis-sentinel', $this->config);
        $app->register(new RedisSentinelServiceProvider($app));

        if (! ApplicationFactory::isLumen()) {
            $app->boot();
        }

        $broadcast = 'Illuminate\Contracts\Broadcasting\Factory';

        $this->subject = $app->make($broadcast)->connection('redis-sentinel');
    }

    /**
     * @group broadcasting
     */
    public function testIsARedisBroadcaster()
    {
        $class = 'Illuminate\Broadcasting\Broadcasters\RedisBroadcaster';

        $this->assertInstanceOf($class, $this->subject);
    }

    /**
     * @group broadcasting
     */
    public function testBroadcastsAnEvent()
    {
        $message1 = $this->makeEventString('test-event-1');
        $message2 = $this->makeEventString('test-event-2');

        $expected = [
            'test-channel-1' => [ $message1, $message2 ],
            'test-channel-2' => [ $message1, $message2 ],
        ];

        $channels = array_keys($expected);

        $this->assertPublishes($expected, function () use ($channels) {
            $this->subject->broadcast($channels, 'test-event-1');
            $this->subject->broadcast($channels, 'test-event-2');
        });
    }

    /**
     * Create the string representation of a message for event broadcast.
     *
     * @param string $id     A name that uniquely identifies the event.
     * @param array  $data   Any event-specific data.
     * @param string $socket The name of the socket to publish for.
     *
     * @return string The event message as JSON.
     */
    protected function makeEventString($id, array $data = [ ], $socket = null)
    {
        $eventData = [ 'event' => $id, 'data' => $data ];

        if (ApplicationFactory::getApplicationVersion() > 5.2) {
            $eventData['socket'] = $socket;
        }

        return json_encode($eventData);
    }
}
