<?php

namespace Monospice\LaravelRedisSentinel\Connections;

use Illuminate\Redis\Connections\PredisConnection;

class PredisSentinelConnection extends PredisConnection
{
    /**
     * Execute commands in a transaction.  Avoids use of Predis transaction
     * which does not support aggregate connections.  Mirrors implementation
     * of PhpRedisConnection::transaction() in core Framework.
     *
     * @param  callable  $callback
     * @return \Redis|array
     */
    public function transaction(callable $callback = null)
    {
        $transaction = $this->client()->multi();

        return is_null($callback)
            ? $transaction
            : tap($this->client(), $callback)->exec();
    }
}