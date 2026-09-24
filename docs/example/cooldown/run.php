<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Timing\ExecutionDeadline;
use ApiSutra\Transport\MockTransport;
use Examples\Cooldown\DemoClient;
use Examples\Cooldown\ExampleClock;
use Examples\Cooldown\ReportRequest;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once __DIR__ . '/DemoClient.php';
require_once __DIR__ . '/ExampleClock.php';
require_once __DIR__ . '/ReportRequest.php';

$clock = new ExampleClock();
$transport = new MockTransport();
$transport->preventStrayRequests();
$transport->fake(['*' => MockResponse::sequence([MockResponse::rateLimited(30), MockResponse::success()])]);
$client = new DemoClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport, sleeper: $clock, clock: $clock);
$client->send(new ReportRequest());
$denied = $client->send(new ReportRequest())->raw();

// Один внешний срок разрешает ждать cooldown и ограничивает импорт целиком.
$deadline = ExecutionDeadline::afterMs(60_000, $clock);
$requests = (static function () use ($deadline): Generator {
    for ($i = 0; $i < 3; $i++) {
        yield (new ReportRequest())->withDeadline($deadline);
    }
})();
$transport->fake(['*' => MockResponse::success(['ready' => true])]);
$summary = $client->pool($requests, 1)->consume();

// Альтернатива: отдельный бюджет каждого элемента, без общего срока импорта.
$perItem = new DemoClient(new ClientConfig(
    baseUrl: 'https://fixture.test',
    retry: new RetryConfig(attempts: 1, totalTimeoutMs: 60_000)
), $transport, sleeper: $clock, clock: $clock);
$perItem->send(new ReportRequest());

$output = ['denial' => $denied->errors->first()?->context['reason'], 'successful' => $summary->successful, 'waits_ms' => $clock->waits];
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo json_encode($output, JSON_THROW_ON_ERROR) . PHP_EOL;
}
return $output;
