<!-- languages --> <a href="testing.md">English</a> · <a href="../../ru/development/testing.md">Русский</a> <!-- /languages -->
# SDK verification <a id="section-1"></a>

## Package checks <a id="section-2"></a>

Run these commands from an ApiSutra source checkout. Package consumers do not need
these tools; a production `composer install --no-dev` excludes them.

The list below is the full verification set, not a required sequence for every edit.
Start with affected scenarios. For shared behavior changes, run the package suite;
for implementation or type changes, also run analysis and lint once ready. Text-only
changes need documentation and whitespace checks; changed PHP examples also need
example analysis and execution. Archive installation, Redis, and dependency/platform
checks apply when the change affects those areas, or for CI and release verification.
Repeat passed checks only after relevant changes, failures, or new evidence.

```bash
composer install
composer validate --strict
composer dump-autoload --optimize --strict-psr
composer test -- --fail-on-deprecation --fail-on-warning
composer lint
composer analyse
composer check-docs
composer analyse-docs
composer check-package
```

`check-docs` and `check-package` require Python 3.9+. The former checks local Markdown
link paths. The latter compares a Git archive of HEAD with a Composer archive of the
working copy, then installs each archive without dev packages and runs external smoke
tests, including the README quickstart PHP example. For uncommitted changes, first
stage the intended files and run `composer check-package -- --staged`. An optional
report can be saved with `--report`; routine checks need no separate report files.

PHPStan checks all src at level 5 with an exact baseline of existing findings. New
errors and stale baseline entries fail the check. This does not claim that all typing
debt is eliminated. PSR-12 is checked for src: errors fail the check, while warnings
about recommended line length remain visible.

CI covers PHP 8.4/8.5, locked/lowest/latest dependencies, quality, archives, and separate [Docs CI](../../../.github/workflows/docs.yml) for
documentation and PHP examples. Composer determines lowest using current compatibility
and security constraints; it does not install known vulnerable historical versions.
To reproduce in a separate checkout:

```bash
composer update --prefer-lowest --prefer-stable --no-interaction
composer test
# Current compatible dependency set:
composer update --prefer-stable --no-interaction
composer test
```

Laravel integration checks belong to [apisutra/laravel](https://github.com/apisutra/laravel/blob/master/docs/en/development/testing.md).
They use a separate application; no adapter checkout is required for core checks.

In the verified lowest set, older Guzzle/PSR-7, PSR HTTP Factory, Symfony Translation,
and deep-copy emit deprecations under PHP 8.4/8.5. This is a recorded limitation of old
dependencies: diagnostics remain visible in a separate CI job. Locked/latest pass
with `--fail-on-deprecation`; lowest retains `--display-deprecations` output without
globally suppressing E_DEPRECATED. Updating compatible dependencies removes these
warnings. Runtime constraints are not narrowed merely to remove old warnings from reports.

## Redis rate limiting <a id="section-3"></a>

From the source repository root after `composer install`:

```bash
composer test:redis
```

Requires working Docker with Compose (for example, Docker Desktop; run from WSL on
Windows). The command builds PHP with phpredis, starts a separate Redis instance,
waits for its healthcheck, and runs Redis tests. Local PHP needs no redis extension.
On the first run, downloading images and building can take several minutes; later
runs reuse the build cache.

After success, failure, or ordinary Ctrl+C interruption, the script removes its run's
containers, volumes, and network. The image remains for reuse. Each run has a separate
Compose project; Redis ports are not published to the host. Source files are mounted
read-only. The test exit code is preserved; cleanup failure also makes the command fail.

Pest options can be passed through:

```bash
composer test:redis -- --filter=NOSCRIPT
```

`composer test` retains ordinary execution without Docker: Redis scenarios are skipped.
The local environment uses PHP 8.4 / phpredis 6.2 / Redis 7.0; CI additionally checks
PHP 8.5 / phpredis 6.3 / Redis 8.2. In the required Redis job, a missing extension or
server is an error rather than a reason to skip tests.

For manual execution with phpredis installed and a dedicated test server:

```bash
APISUTRA_TEST_REDIS=1 APISUTRA_REDIS_HOST=127.0.0.1 APISUTRA_REDIS_PORT=6379 vendor/bin/pest tests/Integration/Redis
```

**Do not point manual checks at the application's Redis:** tests change ACL/maxmemory
and clear the script cache. `composer test:redis` creates its own server and does not
use the Redis address from the application's environment.

The environment is in `tests/Integration/Redis/compose.yaml`; startup and cleanup are
in `tests/Support/test-redis.sh`. Checks cover separate workers with a barrier, shared
quotas, TTL, NOSCRIPT, ACL/OOM, and response loss after command execution. The narrow
test proxy is only for checking an unknown outcome; it does not ship with the SDK.

## Published examples <a id="section-4"></a>

`composer analyse-docs` checks PHP examples with a separate PHPStan configuration.
`composer check-docs` checks declarations and runs the SDK, hydration-rules, and
continuation examples. Laravel binding of the same source files is
analyzed and tested by `apisutra/laravel`. Core PHPStan excludes only the SDK's Laravel
folder. Run core `composer check-package` for standalone installations and the adapter's
checks for Laravel installations; neither discards the other's coverage.

When changing the Records SDK, the adapter API it uses, or their guides, verify both
current checkouts before merging. Follow the adapter’s [joint example checks](https://github.com/apisutra/laravel/blob/master/docs/en/development/testing.md#sdk-example).
A passing adapter CI against a pinned core revision does not verify the changed example.
