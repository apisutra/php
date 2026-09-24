<?php

declare(strict_types=1);

// Измеряет только применитель квот: без HTTP, логов и сборки клиента на каждой попытке.
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RateLimitConfig;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Pipeline\Transport\RateLimitApplier;
use ApiSutra\RateLimiting\Backends\LocalRateLimitBackend;
use ApiSutra\RateLimiting\Backends\PhpRedisRateLimitBackend;
use ApiSutra\RateLimiting\RateLimitBackendInterface;
use ApiSutra\RateLimiting\RateLimitDecision;
use ApiSutra\RateLimiting\RateLimiter;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\VO\Pipeline\PipelineContext;

require __DIR__ . '/../../vendor/autoload.php';

$results = ['php' => PHP_VERSION, 'phpredis' => phpversion('redis') ?: null,
    'conditions' => 'single process, warmed autoload, RateLimitApplier only, no HTTP; microseconds per attempt', 'cases' => []];
$redis = null;
if (getenv('APISUTRA_TEST_REDIS') === '1') {
    $redis = new Redis();
    $redis->connect(getenv('APISUTRA_REDIS_HOST') ?: '127.0.0.1', (int) (getenv('APISUTRA_REDIS_PORT') ?: 6379), 2);
    $results['redis'] = $redis->info('server')['redis_version'];
}
$cases = ['disabled', 'psr16', 'local_one', 'local_two'];
if ($redis !== null) {
    array_push($cases, 'redis_warm', 'redis_cold', 'redis_deny');
}
foreach ($cases as $case) {
    $scope = 'benchmark-' . bin2hex(random_bytes(8));
    $external = str_starts_with($case, 'redis');
    $delegate = $external ? new PhpRedisRateLimitBackend($redis, $scope) : new LocalRateLimitBackend();
    $counter = new class ($delegate) implements RateLimitBackendInterface {
        public int $calls = 0;
        public int $quotaCount = 0;
        public function __construct(private readonly RateLimitBackendInterface $delegate) {}
        public function tryAcquire(array $quotas, ?int $timeoutMs = null): RateLimitDecision
        {
            $this->calls++;
            $this->quotaCount = count($quotas);
            return $this->delegate->tryAcquire($quotas, $timeoutMs);
        }
    };
    $config = new ClientConfig(baseUrl: 'https://benchmark.test',
        rateLimit: $case === 'disabled' ? null : new RateLimitConfig(
            limit: $case === 'redis_deny' ? 1 : 1_000_000, period: 60,
            behavior: RateLimitBehavior::Throw, store: $case === 'psr16' ? new ArrayCache() : null,
        ), rateLimitBackend: $case === 'psr16' ? null : $counter);
    $request = new RetryPolicyRequest();
    $options = $case === 'local_two' || $external ? RequestOptions::empty()->withRateLimit(1_000_000, 60) : null;
    $context = new PipelineContext($request, $config, 'benchmark', RequestRole::Root, options: $options);
    $applier = new RateLimitApplier($config, new RateLimiter(backend: $case === 'psr16' ? null : $counter));
    $applier->apply($request, $context);
    $counter->calls = 0;
    $n = $external ? ($case === 'redis_cold' ? 20 : 500) : 20000;
    $before = $external ? $redis->info('commandstats') : [];
    $memory = memory_get_usage();
    memory_reset_peak_usage();
    $elapsed = 0;
    for ($i = 0; $i < $n; $i++) {
        if ($case === 'redis_cold') {
            $redis->script('flush');
        }
        $start = hrtime(true);
        try {
            $applier->apply($request, $context);
        } catch (RateLimitException) {
            // Отказ намеренно измеряется отдельно от разрешения.
        }
        $elapsed += hrtime(true) - $start;
    }
    $memoryDelta = memory_get_usage() - $memory;
    $peakExtra = memory_get_peak_usage() - $memory;
    $after = $external ? $redis->info('commandstats') : [];
    $calls = static function (array $stats, string $command): int {
        preg_match('/calls=(\d+)/', $stats['cmdstat_' . $command] ?? '', $matches);
        return (int) ($matches[1] ?? 0);
    };
    $results['cases'][$case] = ['attempts' => $n, 'us_per_attempt' => round($elapsed / $n / 1000, 3),
        'backend_calls' => $counter->calls, 'quotas_per_backend_call' => $counter->quotaCount,
        'memory_delta_bytes' => $memoryDelta, 'peak_extra_bytes' => $peakExtra,
        'redis_evalsha' => $calls($after, 'evalsha') - $calls($before, 'evalsha'),
        'redis_eval' => $calls($after, 'eval') - $calls($before, 'eval')];
    if ($external) {
        $keys = $redis->keys('apisutra:rate-limit:v3:' . hash('sha256', $scope) . ':*');
        if ($keys !== []) {
            $redis->del($keys);
        }
    }
}
echo json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
