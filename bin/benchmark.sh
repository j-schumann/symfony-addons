#!/usr/bin/env bash
#
# Benchmarks the database refresh that RefreshDatabaseTrait performs on each
# bootKernel(), for every supported database platform (rows) and every supported
# cleanup method (columns).
#
# Locally, run it inside the php container of .dev-symfony/docker-compose.yml:
#
#   docker compose -f .dev-symfony/docker-compose.yml up -d
#   docker exec -w /var/www/html php-symfony bin/benchmark.sh
#
# For the tmpfs comparison, start the containers with the override as well:
#
#   docker compose -f .dev-symfony/docker-compose.yml \
#                  -f .dev-symfony/docker-compose.tmpfs.yml up -d
#   BENCH_STORAGE=tmpfs docker exec -w /var/www/html php-symfony bin/benchmark.sh
#
# In CI the workflow provides the services and sets the DATABASE_URL_* variables.
#
# Environment:
#   BENCH_PLATFORMS   space separated subset of the platforms below
#   BENCH_ITERATIONS  kernel boots per cell (default 50)
#   BENCH_STORAGE     label for the report only, e.g. "disk" or "tmpfs"
#   BENCH_REPORT      file to append the markdown report to
#   DATABASE_URL_<PLATFORM>  overrides the built-in DSN for that platform

set -u -o pipefail

ITERATIONS="${BENCH_ITERATIONS:-50}"
STORAGE="${BENCH_STORAGE:-disk}"
REPORT="${BENCH_REPORT:-}"
PLATFORMS="${BENCH_PLATFORMS:-sqlite mariadb mysql postgres sqlsrv}"

# The cleanup methods, as "<label>|<DB_CLEANUP_METHOD>|<DB_PURGE_MODE>". The purge
# mode only has an effect on MySQL/MariaDB, on the other platforms both purge
# columns measure the same thing.
METHODS=(
    "purge (delete)|purge|delete"
    "purge (truncate)|purge|truncate"
    "dropSchema|dropSchema|delete"
    "dropDatabase|dropDatabase|delete"
)

default_dsn() {
    case "$1" in
        sqlite)   echo "sqlite:///${BENCH_SQLITE_PATH:-%kernel.project_dir%/var/bench.db}" ;;
        mariadb)  echo "mysql://db_test:db_test@mariadb:3306/db_test?serverVersion=mariadb-12.0.0&charset=utf8mb4" ;;
        mysql)    echo "mysql://db_test:db_test@mysql:3306/db_test?serverVersion=9.0&charset=utf8mb4" ;;
        postgres) echo "pgsql://db_test:db_test@pgsql/db_test?serverVersion=18" ;;
        sqlsrv)   echo "sqlsrv://sa:StrongP-ssw0rd!@mssql-symfony:1433/db_test?serverVersion=16&charset=utf-8&driverOptions[Encrypt]=0" ;;
        *)        echo "" ;;
    esac
}

dsn_for() {
    local platform="$1"
    local override
    override="DATABASE_URL_$(echo "$platform" | tr '[:lower:]' '[:upper:]')"

    if [ -n "${!override:-}" ]; then
        echo "${!override}"
    else
        default_dsn "$platform"
    fi
}

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

declare -A RESULTS
declare -A FIRST_BOOT

echo "Refresh benchmark: ${ITERATIONS} kernel boots per cell, storage=${STORAGE}"
echo

for platform in $PLATFORMS; do
    dsn="$(dsn_for "$platform")"
    if [ -z "$dsn" ]; then
        echo "!! unknown platform '$platform', skipping"
        continue
    fi

    for entry in "${METHODS[@]}"; do
        label="${entry%%|*}"
        rest="${entry#*|}"
        method="${rest%%|*}"
        purge_mode="${rest##*|}"

        out="$TMP_DIR/$platform-$method-$purge_mode.json"
        printf '  %-9s %-18s ' "$platform" "$label"

        # RefreshDatabaseTrait reads its settings from $_ENV, which PHP only fills
        # from the real environment when variables_order contains an "E". The
        # php.ini default is "GPCS", so without this the settings below would be
        # silently ignored and every cell would measure the default instead.
        if ! DATABASE_URL="$dsn" \
            DB_CLEANUP_METHOD="$method" \
            DB_PURGE_MODE="$purge_mode" \
            BENCH_PLATFORM="$platform" \
            BENCH_ITERATIONS="$ITERATIONS" \
            BENCH_OUTPUT="$out" \
            "${BENCH_PHP:-php}" -d variables_order=EGPCS \
            vendor/bin/phpunit --group benchmark --no-output > "$TMP_DIR/log" 2>&1
        then
            echo "FAILED"
            tail -5 "$TMP_DIR/log" | sed 's/^/      /'
            RESULTS["$platform|$label"]="failed"
            continue
        fi

        if [ ! -f "$out" ]; then
            echo "no result"
            RESULTS["$platform|$label"]="n/a"
            continue
        fi

        # Guard against measuring the wrong thing: the run reports back which
        # settings it actually saw, a mismatch means they did not reach the trait.
        seen_method="$(sed -n 's/.*"cleanupMethod": "\([^"]*\)".*/\1/p' "$out")"
        seen_mode="$(sed -n 's/.*"purgeMode": "\([^"]*\)".*/\1/p' "$out")"
        if [ "$seen_method" != "$method" ] || [ "$seen_mode" != "$purge_mode" ]; then
            echo "MISCONFIGURED: asked for $method/$purge_mode, run saw $seen_method/$seen_mode"
            echo "      the settings never reached the trait, aborting rather than reporting bogus numbers"
            exit 1
        fi

        median="$(sed -n 's/.*"medianMs": \([0-9.]*\).*/\1/p' "$out")"
        first="$(sed -n 's/.*"firstBootMs": \([0-9.]*\).*/\1/p' "$out")"
        RESULTS["$platform|$label"]="$median"
        FIRST_BOOT["$platform|$label"]="$first"
        echo "${median} ms"
    done
done

emit() {
    echo
    echo "### Database refresh per bootKernel() — ${STORAGE}, median of ${ITERATIONS} boots (ms)"
    echo
    printf '| platform |'
    for entry in "${METHODS[@]}"; do printf ' %s |' "${entry%%|*}"; done
    printf '\n| --- |'
    for _ in "${METHODS[@]}"; do printf ' ---: |'; done
    printf '\n'

    for platform in $PLATFORMS; do
        printf '| %s |' "$platform"
        for entry in "${METHODS[@]}"; do
            printf ' %s |' "${RESULTS[$platform|${entry%%|*}]:-n/a}"
        done
        printf '\n'
    done

    echo
    echo "The first boot of a process also creates the database and the schema and is"
    echo "excluded from the median; on the fastest cells it costs more than all the"
    echo "other boots together."
}

emit
if [ -n "$REPORT" ]; then
    emit >> "$REPORT"
fi
