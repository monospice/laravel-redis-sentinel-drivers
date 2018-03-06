#!/bin/sh
#
# Start a set of local Redis and Sentinel servers.
#
# Usage: [OPTION=VALUE]... ./start-cluster.sh [config|help]
#
# ---
#
# Package: Laravel Drivers for Redis Sentinel
# Author:  Cy Rossignol <cy@rossignols.me>
# Website: https://github.com/monospice/laravel-redis-sentinel-drivers
# License: The MIT License (MIT)
#
# Copyright (c) Monospice
#
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in all
# copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.
#

WORKDIR="${WORKDIR:-./cluster}"
BIND_ADDRESS="${BIND_ADDRESS:-127.0.0.1}"
SENTINEL_PORTS="${SENTINEL_PORTS:-26379-26381}"
DOWN_AFTER="${DOWN_AFTER-3000}"
FAILOVER_TIMEOUT="${FAILOVER_TIMEOUT-10000}"
PARALLEL_SYNCS="${PARALLEL_SYNCS-1}"
TRUNCATE_LOGS="${TRUNCATE_LOGS:-yes}"
CLEANUP="${CLEANUP:-yes}"
SUPERVISE="${SUPERVISE:-yes}"
LOGGING="${LOGGING:-no}"

if [ -z "$REDIS_GROUP_1" ] && [ -z "$REDIS_GROUP_2" ] \
    && [ -z "$REDIS_GROUP_3" ] && [ -z "$REDIS_GROUP_4" ] \
    && [ -z "$REDIS_GROUP_5" ] && [ -z "$REDIS_GROUP_6" ] \
    && [ -z "$REDIS_GROUP_7" ] && [ -z "$REDIS_GROUP_8" ] \
    && [ -z "$REDIS_GROUP_9" ]
then
    REDIS_GROUP_1='service1 6379-6381'
    REDIS_GROUP_2='service2 6382-6384'
fi

if [ "$*" = 'config' ]; then
    printf "%s='%s'\\n" \
        'WORKDIR' "$WORKDIR" \
        'BIND_ADDRESS' "$BIND_ADDRESS" \
        'SENTINEL_PORTS' "$SENTINEL_PORTS" \
        'REDIS_GROUP_1' "$REDIS_GROUP_1" \
        'REDIS_GROUP_2' "$REDIS_GROUP_2" \
        'REDIS_GROUP_3' "$REDIS_GROUP_3" \
        'REDIS_GROUP_4' "$REDIS_GROUP_4" \
        'REDIS_GROUP_5' "$REDIS_GROUP_5" \
        'REDIS_GROUP_6' "$REDIS_GROUP_6" \
        'REDIS_GROUP_7' "$REDIS_GROUP_7" \
        'REDIS_GROUP_8' "$REDIS_GROUP_8" \
        'REDIS_GROUP_9' "$REDIS_GROUP_9" \
        'SENTINEL_CONF' "$SENTINEL_CONF" \
        'REDIS_CONF' "$REDIS_CONF" \
        'TRUNCATE_LOGS' "$TRUNCATE_LOGS" \
        'CLEANUP' "$CLEANUP" \
        'SUPERVISE' "$SUPERVISE" \
        'LOGGING' "$LOGGING"

    exit 0
fi

if [ -n "$*" ]; then
    printf '
Start a set of local Redis and Sentinel servers.

  Usage: [OPTION=VALUE]... %s [config|help]

This script accepts two arguments:

  config            Display the current values of the configuration options.
  help              Show this help message.

Options (from environment variables):

  WORKDIR           The directory to place server logs and runtime files in.
  BIND_ADDRESS      Address of the interface to listen on (default: 127.0.0.1).
  SENTINEL_PORTS    Comma-separated port numbers or ranges for each Sentinel.
  REDIS_GROUP_1..9  Group name and port numbers/ranges for a Redis master group
                    monitored by Sentinel.
  DOWN_AFTER        Milliseconds after which Sentinel considers a master down.
  FAILOVER_TIMEOUT  Milliseconds after which Sentinel retries a failover.
  PARALLEL_SYNCS    No. of replicas to resync simultaneously during failover.
  SENTINEL_CONF     Path to optional Sentinel server configuration file.
  REDIS_CONF        Path to optional Redis server configuration file.
  TRUNCATE_LOGS     Clear any existing logs on start-up (default: "yes").
  CLEANUP           Remove server-created files except logs (default: "yes").
  SUPERVISE         Stay in foreground (default: "yes").
  LOGGING           Output server logs in supervised mode (default: "no").

With the default options, this tool creates the working directory in ./cluster
and starts three Sentinels and two groups of three Redis servers. The script
supports basic configuration by setting the environment variables shown above.
The following example shows how we can change the configuration by setting the
server working directory to /tmp/redis and starting only one Sentinel server:

  WORKDIR=/tmp/redis SENTINEL_PORTS=26379 %s

The script starts a Sentinel instance for each port defined in SENTINEL_PORTS
and a Redis instance for every port defined in each assigned REDIS_GROUP_N
variable (where N is an integer in the range of 1 through 9). The generic
format expected for this variable is shown below:

  REDIS_GROUP_N="group-name port[,port,port-range,...]"

For example:

  REDIS_GROUP_1="mymaster 6379-6381,6400"
  REDIS_GROUP_2="cache 6382-6384"

The script will initialize a Redis master for the first port in each group and
replicas for any remaining ports.

The value of SUPERVISE determines whether the script should remain in the
foreground after starting the servers. This allows us to stop the cluster at
once by pressing Ctrl-C. The script runs in supervised mode by default. To
disable this behavior, set SUPERVISE to "no":

To stop the servers in non-supervised mode, we can send the TERM signal to each
Redis process by finding the PIDs from the working directory:

  kill $(cat %s/*.pid)

This script is suitable for the entrypoint command used to start a Docker
container test environment. Read the "Testing" section of the README for more
information.\n\n' "$0" "$0" "$WORKDIR" >&2

    exit 1
fi

if ! command -v redis-cli > /dev/null; then
    printf 'ERROR: Cannot find redis-cli. Verify that Redis is installed.' >&2

    exit 1
fi

start_redis() {
    printf 'Starting Redis server on port %d (%s)...\n' "$2" "$1" || return $?

    assert_not_listening "$2"

    if is_true "$TRUNCATE_LOGS"; then
        printf '' > "$WORKDIR/redis-$2.log"
    fi

    master_port="$3"

    set -- --port "$2" \
        --daemonize yes \
        --bind $BIND_ADDRESS \
        --dir "$WORKDIR" \
        --pidfile "redis-$2.pid" \
        --logfile "redis-$2.log" \
        --dbfilename "dump-$2.rdb" \
        --appendfilename "appendonly-$2.aof"

    if [ "$2" -ne "$master_port" ]; then
        set -- "$@" --slaveof 127.0.0.1 "$master_port"
    fi

    if [ -n "$REDIS_CONF" ]; then
        cp "$REDIS_CONF" "$WORKDIR/redis-$2.conf" || return $?
        set -- "$WORKDIR/redis-$2.conf" "$@"
    fi

    redis-server "$@"
}

start_sentinel() {
    printf 'Starting Sentinel server on port %d...\n' "$1" || return $?

    assert_not_listening "$1"

    if is_true "$TRUNCATE_LOGS"; then
        printf '' > "$WORKDIR/sentinel-$1.log"
    fi

    write_sentinel_conf "$1" || return $?

    redis-server "$WORKDIR/sentinel-$1.conf" --sentinel \
        --daemonize yes \
        --bind $BIND_ADDRESS \
        --port "$1" \
        --dir "$WORKDIR" \
        --pidfile "sentinel-$1.pid" \
        --logfile "sentinel-$1.log"
}

start_group() {
    case "$1" in *[!A-Za-z0-9.-_]*)
        printf 'ERROR: Invalid master group name: %s\n' "$1" >&2 && return 1
    esac

    group_ports="$(IFS=',' parse_ports "$2")" || return $?
    master="${group_ports%% *}"
    Redis_Ports="$Redis_Ports $group_ports"

    get_group_conf "$1" "$master" >> "$WORKDIR/sentinel-base.conf" || return $?

    for port in $group_ports; do
        start_redis "$1" "$port" "$master" || return $?
        assert_listening "$port"
    done

    verify_replication "$group_ports" & Verify_Pids="$Verify_Pids $!"
    verify_synchronization "$group_ports" & Verify_Pids="$Verify_Pids $!"
}

get_group_conf() {
    printf 'sentinel monitor %s 127.0.0.1 %d %d\n' "$1" "$2" "$Sentinel_Count"

    if [ -n "$DOWN_AFTER" ]; then
        printf 'sentinel down-after-milliseconds %s %d\n' "$1" "$DOWN_AFTER"
    fi

    if [ -n "$FAILOVER_TIMEOUT" ]; then
        printf 'sentinel failover-timeout %s %d\n' "$1" "$FAILOVER_TIMEOUT"
    fi

    if [ -n "$PARALLEL_SYNCS" ]; then
        printf 'sentinel parallel-syncs %s %d\n' "$1" "$PARALLEL_SYNCS"
    fi
}

write_sentinel_conf() {
    cp "$WORKDIR/sentinel-base.conf" "$WORKDIR/sentinel-$1.conf" || return $?

    if [ -z "$SENTINEL_CONF" ]; then
        return 0
    fi

    while IFS='' read -r line || [ -n "$line" ]; do
        case "$line" in *'sentinel monitor '*)
            continue ;;
        esac

        printf '%s\n' "$line" >> "$WORKDIR/sentinel-$1.conf" || return $?
    done < "$SENTINEL_CONF"
}

assert_listening() {
    for timeout in 1 2 3 4 5 9; do
        redis-cli -p "$1" PING > /dev/null 2>&1 && return 0
        sleep "0.$timeout" 2> /dev/null || sleep 1
    done

    redis-cli -p "$1" PING > /dev/null || terminate $?
}

assert_not_listening() {
   (reply="$(redis-cli -p "$1" PING 2>&1)" \
        || [ "${reply%*Connection refused}" = "$reply" ]) &

    pid=$!

    for timeout in 1 2 3 4 5; do
        kill -0 "$pid" 2> /dev/null || break
        sleep "0.$timeout" 2> /dev/null || sleep 1
    done

    if kill "$pid" 2> /dev/null || wait "$pid"; then
        printf 'ERROR: Port %d already in use.\n' "$1" >&2
        terminate 1
    fi
}

parse_ports() {
    for ports in $1; do
        end_port="${ports#*-}"
        port="${ports%-*}"

        if ! [ "$end_port" -ge "$port" ] 2> /dev/null \
            || [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then
            printf 'ERROR: Invalid port or range: %s\n' "$ports" >&2

            return 1
        fi

        until [ "$port" -gt "$end_port" ]; do
            printf '%d ' "$port"
            port="$(( port + 1 ))"
        done
    done
}

count_items() {
    set -- $1
    printf '%d' $#
}

is_true() {
    case "$1" in 1|[Yy]|yes|true)
        return 0 ;;
    esac

    return 1
}

wait_for_servers() {
    while [ $# -gt 0 ]; do
        if kill -0 "$@" 2> /dev/null && sleep 2; then
            continue
        fi

        for pid in "$@"; do
            shift

            if kill -0 "$pid" 2> /dev/null; then
                set -- "$@" "$pid"
            else
                printf 'WARNING: Process %d stopped unexpectedly.\n' "$pid" >&2
            fi
        done

        sleep 2
    done

    printf 'ERROR: No servers running.\n' >&2
}

verify_replication() {
    master_port="${1%% *}"
    replica_count="$(count_items "${1#* }")"

    for timeout in 0 1 1 2 3 3; do
        sleep "$timeout"

        info="$(redis-cli -p "$master_port" INFO replication)" || return $?

        case "$info" in *connected_slaves:${replica_count}[$(printf '\r\n')]*)
            return 0 ;;
        esac
    done

    printf '\nReplicas failed to connect after 10 seconds.\n' >&2

    return 1
}

verify_synchronization() {
    replica_ports="${1#* }"
    replica_count="$(count_items "$replica_ports")"

    for timeout in 0 1 1 2 3 3; do
        sleep "$timeout"

        finished_sync_count=0

        for port in $replica_ports; do
            info="$(redis-cli -p "$port" INFO replication)" || return $?

            case "$info" in *master_sync_in_progress:0*)
                finished_sync_count="$(( finished_sync_count + 1 ))"
            esac
        done;

        if [ "$finished_sync_count" -eq "$replica_count" ]; then
            return 0
        fi
    done

    printf '\nReplicas did not finish synchronization after 10 seconds.\n' >&2

    return 1
}

verify_quorum() {
    printf 'Waiting for Sentinel quorum consensus...'

    for timeout in 1 1 2 3 3; do
        sleep "$timeout"
        ok_groups=''

        for group in $Group_Names; do
            reply="$(redis-cli -p "${SENTINEL_PORTS%% *}" \
                SENTINEL ckquorum "$group")" || return $?

            if [ "${reply%% *}" != 'OK' ]; then
                break
            fi

            ok_groups="$ok_groups $group"
        done

        if [ "$ok_groups" = "$Group_Names" ]; then
            printf 'done.\n' && return 0
        fi
    done

    printf '\nERROR: Could not achieve quorum after 10 seconds.\n' >&2

    return 1
}

start_logger() {
    if ! command -v tail > /dev/null; then
        printf 'WARNING: Cannot find the tail program. Logging disabled.\n' >&2

        return 1
    fi

    Log_Pids=''
    printf 'Watching server logs in %s...\n' "$WORKDIR"

    for port in $Redis_Ports $SENTINEL_PORTS; do
        tail -n 50 -f "$WORKDIR/"*"-$port.log" | while IFS='' read -r line; do
            printf '%5s: %s\n' "$port" "$line"
        done &

        Log_Pids="$Log_Pids $!"
    done
}

terminate() {
    trap - INT TERM
    unset IFS

    if [ -n "$Log_Pids$Verify_Pids" ]; then
        kill $Log_Pids $Verify_Pids 2> /dev/null
    fi

    sleep 1

    if server_pids="$(cat "$WORKDIR/"*.pid 2> /dev/null)"; then
        printf 'Stopping %s...\n' "$WORKDIR/"*.pid 2> /dev/null

        kill $server_pids 2> /dev/null
        wait_for_servers $server_pids 2> /dev/null
    fi

    if is_true "$CLEANUP"; then
        rm -f "$WORKDIR/"*.pid "$WORKDIR/"*.conf "$WORKDIR/"*.rdb
    fi

    exit "$1"
}

SENTINEL_PORTS="$(IFS=',' parse_ports "$SENTINEL_PORTS")" || exit $?
Sentinel_Count="$(count_items "$SENTINEL_PORTS")"
Redis_Ports=''
Group_Names=''
Verify_Pids=''

mkdir -p "$WORKDIR" || exit $?
printf '' > "$WORKDIR/sentinel-base.conf" || exit $?

trap 'terminate 130' INT
trap 'terminate 143' TERM

for Group in \
    "$REDIS_GROUP_1" "$REDIS_GROUP_2" "$REDIS_GROUP_3" "$REDIS_GROUP_4" \
    "$REDIS_GROUP_5" "$REDIS_GROUP_6" "$REDIS_GROUP_7" "$REDIS_GROUP_8" \
    "$REDIS_GROUP_9"
do
    if [ -n "$Group" ]; then
        start_group "${Group%% *}" "${Group#* }" || terminate $?
        Group_Names="$Group_Names ${Group%% *}"
    fi
done

unset IFS

for Port in $SENTINEL_PORTS; do
    start_sentinel "$Port" || terminate $?
    assert_listening "$Port"
done

printf 'Waiting for replicas to synchronize...'
wait $Verify_Pids && printf 'done.\n' || terminate $?
unset Verify_Pids

verify_quorum || terminate $?

if is_true "$SUPERVISE"; then
    printf 'Press Ctrl-C to stop...\n'
    trap 'printf "Shutting down...\n"; terminate 0' INT TERM

    server_pids="$(cat "$WORKDIR/"*.pid)" || exit $?

    if is_true "$LOGGING"; then
        start_logger
    fi

    wait_for_servers $server_pids
fi
