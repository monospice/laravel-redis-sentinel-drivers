<?php

namespace Monospice\LaravelRedisSentinel\Connections;

use Closure;
use Illuminate\Redis\Connections\PredisConnection as LaravelPredisConnection;
use Monospice\SpicyIdentifiers\DynamicMethod;
use Predis\ClientInterface as Client;
use Predis\CommunicationException;
use Predis\PubSub\Consumer as PubSub;
use RuntimeException;

/**
 * Executes Redis commands using the Predis client.
 *
 * This package extends Laravel's PredisConnection class to work around issues
 * experienced when using the Predis client to send commands over "aggregate"
 * connections (in this case, Sentinel connections).
 *
 * @category Package
 * @package  Monospice\LaravelRedisSentinel
 * @author   @pdbreen, Cy Rossignol <cy@rossignols.me>
 * @license  See LICENSE file
 * @link     https://github.com/monospice/laravel-redis-sentinel-drivers
 */
class PredisConnection extends LaravelPredisConnection
{
    /**
     * The number of times the client attempts to retry a command when it fails
     * to connect to a Redis instance behind Sentinel.
     *
     * @var int
     */
    protected $retryLimit = 20;

    /**
     * The time in milliseconds to wait before the client retries a failed
     * command.
     *
     * @var int
     */
    protected $retryWait = 1000;

    /**
     * Create a Redis Sentinel connection using a Predis client.
     *
     * @param Client $client          The Redis client to wrap.
     * @param array  $sentinelOptions Sentinel-specific connection options.
     */
    public function __construct(Client $client, array $sentinelOptions = [ ])
    {
        parent::__construct($client);

        // Set the Sentinel-specific connection options on the Predis Client
        // connection and the current instance of this class.
        foreach ($sentinelOptions as $option => $value) {
            DynamicMethod::parseFromUnderscore($option)
                ->prepend('set')
                ->callOn($this, [ $value ]);
        }
    }

    /**
     * Set the default amount of time to wait before determining that a
     * connection attempt to a Sentinel server failed.
     *
     * @param float $seconds The timeout value in seconds.
     *
     * @return $this The current instance for method chaining.
     */
    public function setSentinelTimeout($seconds)
    {
        $this->client->getConnection()->setSentinelTimeout($seconds);

        return $this;
    }

    /**
     * Set the default number of attempts to retry a command when the client
     * fails to connect to a Redis instance behind Sentinel.
     *
     * @param int $attempts With a value of 0, throw an exception after the
     * first failed attempt. Pass a value of -1 to retry connections forever.
     *
     * @return $this The current instance for method chaining.
     */
    public function setRetryLimit($attempts)
    {
        $this->retryLimit = (int) $attempts;
        $this->client->getConnection()->setRetryLimit($attempts);

        return $this;
    }

    /**
     * Set the time to wait before retrying a command after a connection
     * attempt failed.
     *
     * @param int $milliseconds The wait time in milliseconds. When 0, retry
     * a failed command immediately.
     *
     * @return $this The current instance for method chaining.
     */
    public function setRetryWait($milliseconds)
    {
        $this->retryWait = (int) $milliseconds;
        $this->client->getConnection()->setRetryWait($milliseconds);

        return $this;
    }

    /**
     * Set whether the client should update the list of known Sentinels each
     * time it needs to connect to a Redis server behind Sentinel.
     *
     * @param bool $enable If TRUE, fetch the updated Sentinel list.
     *
     * @return $this The current instance for method chaining.
     */
    public function setUpdateSentinels($enable)
    {
        $this->client->getConnection()->setUpdateSentinels($enable);

        return $this;
    }

    /**
     * Subscribe to a set of given channels for messages.
     *
     * @param array|string $channels The names of the channels to subscribe to.
     * @param Closure      $callback Executed for each message. Receives the
     * message string in the first argument and the message channel as the
     * second argument. Return FALSE to unsubscribe.
     * @param string       $method   The subscription command ("subscribe" or
     * "psubscribe").
     *
     * @return void
     */
    public function createSubscription(
        $channels,
        Closure $callback,
        $method = 'subscribe'
    ) {
        $this->retryOnFailure(function () use ($method, $channels, $callback) {
            $loop = $this->pubSubLoop([ $method => (array) $channels ]);

            if ($method === 'psubscribe') {
                $messageKind = 'pmessage';
            } else {
                $messageKind = 'message';
            }

            $this->consumeMessages($loop, $messageKind, $callback);

            unset($loop);
        });
    }

    /**
     * Create a new PUB/SUB subscriber and pass messages to the callback if
     * provided.
     *
     * WARNING: Consumers created using this method are not monitored for
     * connection failures. For Sentinel support, use one of the methods
     * provided by the Laravel API instead (subscribe() and psubscribe()).
     *
     * @param array|null $options  Configures the channel(s) to subscribe to.
     * @param callable   $callback Optional callback executed for each message
     * published to the configured channel(s).
     *
     * @return \Predis\PubSub\Consumer|null A PUB/SUB context used to create
     * a subscription loop if no callback provided.
     */
    public function pubSubLoop($options = null, $callback = null)
    {
        // Messages published to the master propagate to each of the slaves. We
        // pick a random slave to distribute load away from the master:
        return $this->getRandomSlave()->pubSubLoop($options, $callback);
    }

    /**
     * Execute commands in a transaction.
     *
     * This package overrides the transaction() method to work around a
     * limitation in the Predis API that disallows transactions on "aggregate"
     * connections like Sentinel. Note that transactions execute on the Redis
     * master instance.
     *
     * @param callable|null $callback Contains the Redis commands to execute in
     * the transaction. The callback receives a Predis\Transaction\MultiExec
     * transaction abstraction as the only argument. We use this object to
     * execute Redis commands by calling its methods just like we would with
     * the Laravel Redis service.
     *
     * @return array|Predis\Transaction\MultiExec An array containing the
     * result for each command executed during the transaction. If no callback
     * provided, returns an instance of the Predis transaction abstraction.
     */
    public function transaction(callable $callback = null)
    {
        return $this->retryOnFailure(function () use ($callback) {
            return $this->getMaster()->transaction($callback);
        });
    }

    /**
     * Attempt to retry the provided operation when the client fails to connect
     * to a Redis server.
     *
     * We adapt Predis' Sentinel connection failure handling logic here to
     * reproduce the high-availability mode provided by the actual client. To
     * work around "aggregate" connection limitations in Predis, this class
     * provides methods that don't use the high-level Sentinel connection API
     * of Predis directly, so it needs to handle connection failures itself.
     *
     * @param callable $callback The operation to execute.
     *
     * @return mixed The result of the first successful attempt.
     *
     * @throws CommunicationException After exhausting the allowed number of
     * attempts to reconnect.
     */
    protected function retryOnFailure(callable $callback)
    {
        $attempts = 0;

        do {
            try {
                return $callback();
            } catch (CommunicationException $exception) {
                $exception->getConnection()->disconnect();
                $this->client->getConnection()->querySentinel();

                usleep($this->retryWait * 1000);

                $attempts++;
            }
        } while ($attempts <= $this->retryLimit);

        throw $exception;
    }

    /**
     * Execute the provided callback for each message read by the PUB/SUB
     * consumer.
     *
     * @param PubSub  $loop     Reads the messages published to a channel.
     * @param string  $kind     The subscribed message type ([p]message).
     * @param Closure $callback Executed for each message.
     *
     * @return void
     */
    protected function consumeMessages(PubSub $loop, $kind, Closure $callback)
    {
        foreach ($loop as $message) {
            if ($message->kind === $kind) {
                if ($callback($message->payload, $message->channel) === false) {
                    return;
                }
            }
        }
    }

    /**
     * Get a Predis client instance for the master.
     *
     * @return Client The client instance for the current master.
     */
    protected function getMaster()
    {
        return $this->client->getClientFor('master');
    }

    /**
     * Get a Predis client instance for a random slave.
     *
     * @param bool $fallbackToMaster If TRUE, return a client for the master
     * if the connection does not include any slaves.
     *
     * @return Client The client instance for the selected slave.
     *
     * @throws RuntimeException When the client cannot reach any replicas
     * (and the master if $fallbackToMaster is TRUE).
     */
    protected function getRandomSlave($fallbackToMaster = true)
    {
        $slaves = $this->client->getConnection()->getSlaves();

        if (count($slaves) > 0) {
            $slave = $slaves[rand(1, count($slaves)) - 1];

            return $this->client->getClientFor($slave->getParameters()->alias);
        }

        if ($fallbackToMaster) {
            return $this->getMaster();
        }

        throw new RuntimeException('No slave present on connection.');
    }
}
