<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CooldownConfig;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\RateLimiting\Cooldown\Backends\PhpRedisCooldownBackend;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\MockTransport;

require __DIR__ . '/../../vendor/autoload.php';
[$script, $host, $port, $scope, $mode, $argument] = $argv;
$redis = new Redis();
$redis->connect($host, (int) $port, 2);
$backend = new PhpRedisCooldownBackend($redis, $scope);
if ($mode === 'extend') {
    echo json_encode($backend->extend('shared', (int) $argument), JSON_THROW_ON_ERROR);
    exit;
}
$clock = new VirtualClock();
$clock->milliseconds += (int) $argument;
$clock->wallTime += (int) $argument;
$transport = new MockTransport();
$transport->fake(['*' => $mode === 'publish' ? MockResponse::rateLimited(30) : MockResponse::success()]);
$client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', cooldownBackend: $backend,
    cooldown: new CooldownConfig(behavior: RateLimitBehavior::Throw)), $transport, $clock, $clock);
$result = $client->send(new RetryPolicyRequest())->raw();
echo json_encode(['status' => $result->response?->status, 'reason' => $result->errors->first()?->context['reason'] ?? null,
    'http' => count($transport->getRecorded()), 'trace' => $result->traceId], JSON_THROW_ON_ERROR);
