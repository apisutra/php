<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CooldownConfig;
use ApiSutra\RateLimiting\Cooldown\Backends\LocalCooldownBackend;
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
$backend = new LocalCooldownBackend($clock);
$config = new ClientConfig(baseUrl: 'https://fixture.test', cooldownBackend: $backend);
$firstTransport = new MockTransport();
$firstTransport->fake(['*' => MockResponse::rateLimited(30)]);
$secondTransport = new MockTransport();
$secondTransport->fake(['*' => MockResponse::success()]);
$first = new DemoClient($config, $firstTransport, sleeper: $clock, clock: $clock);
$second = new DemoClient($config, $secondTransport, sleeper: $clock, clock: $clock);
$first->send(new ReportRequest());
$denied = $second->send(new ReportRequest())->raw();
// Явная другая группа независима; её задаёт SDK по контракту провайдера.
$independent = $second->send(new ReportRequest()->withCooldown(new CooldownConfig(group: 'other')))->raw();
// Два клиента используют общий срок, но сохраняют собственные результаты и trace.
$ready = $second->send(new ReportRequest()->withDeadline(ExecutionDeadline::afterMs(60_000, $clock)))->raw();
$output = ['denial' => $denied->errors->first()?->context['reason'], 'response' => $denied->response,
    'independent' => $independent->isSuccess(), 'ready' => $ready->isSuccess(), 'waits_ms' => $clock->waits];
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo json_encode($output, JSON_THROW_ON_ERROR) . PHP_EOL;
}
return $output;
