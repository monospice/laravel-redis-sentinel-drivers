<?php

namespace Monospice\LaravelRedisSentinel\Tests\Support;

use Closure;
use Predis\ClientInterface;

/**
 * Subscribes to Redis PUB/SUB channels and captures any messages received.
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     http://github.com/monospice/laravel-redis-sentinel-drivers
 */
class PubSubReader
{
    /**
     * The Predis client instance used to subscribe to the channel(s). Configure
     * with a low read timeout to avoid blocking the test execution when a test
     * fails to publish the expected number of messages.
     *
     * @var ClientInterface
     */
    protected $client;

    /**
     * Create a new reader using the provided client.
     *
     * @param ClientInterface $client The client instance used to subscribe
     * to the channel(s). Configure it with a low read timeout.
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Capture any messages received on the provided channel(s).
     *
     * @param array   $channels     The channels to subscribe to.
     * @param int     $messageCount The number of expected messages to wait for.
     * @param Closure $callback     Publishes the expected messages.
     *
     * @return array Multi-dimensional array of the messages received keyed by
     * the channels they were received on.
     */
    public function capture(array $channels, $messageCount, Closure $callback)
    {
        $subscribedChannels = [ ];
        $messages = [ ];

        $loop = $this->client->pubSubLoop([ 'psubscribe' => $channels ]);

        foreach ($loop as $message) {
            if ($message->kind === 'psubscribe') {
                $subscribedChannels[$message->channel] = true;

                if (count($channels) === count($subscribedChannels)) {
                    $callback();
                }

                continue;
            }

            $messages[$message->channel][] = $message->payload;

            if (--$messageCount === 0) {
                break;
            }
        }

        unset($loop);

        return $messages;
    }
}
