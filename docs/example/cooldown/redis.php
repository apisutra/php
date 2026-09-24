<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CooldownConfig;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\RateLimiting\Cooldown\Backends\PhpRedisCooldownBackend;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Examples\Cooldown\DemoClient;
use Examples\Cooldown\ReportRequest;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once __DIR__ . '/DemoClient.php';
require_once __DIR__ . '/ReportRequest.php';

$mode = $argv[1] ?? 'read';
if (!in_array($mode, ['publish', 'read'], true)) {
    throw new InvalidArgumentException('Use publish or read.');
}
$redis = new Redis();
$redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int) (getenv('REDIS_PORT') ?: 6379), 1);
$backend = new PhpRedisCooldownBackend($redis, scope: getenv('APISUTRA_COOLDOWN_SCOPE') ?: 'example');
$transport = new MockTransport();
$transport->fake(['*' => $mode === 'publish' ? MockResponse::rateLimited(30) : MockResponse::success()]);
$client = new DemoClient(new ClientConfig(
    baseUrl: 'https://fixture.test',
    cooldownBackend: $backend,
    cooldown: new CooldownConfig(behavior: RateLimitBehavior::Throw)
), $transport);
$result = $client->send(new ReportRequest())->raw();
echo json_encode(['http' => count($transport->getRecorded()), 'status' => $result->response?->status,
    'reason' => $result->errors->first()?->context['reason'] ?? null], JSON_THROW_ON_ERROR) . PHP_EOL;
