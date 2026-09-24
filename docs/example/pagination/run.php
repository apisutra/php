<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Examples\Pagination\DemoClient;
use Examples\Pagination\PagesRequest;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once __DIR__ . '/DemoClient.php';
require_once __DIR__ . '/PagesRequest.php';

$transport = new MockTransport();
$transport->preventStrayRequests();
$transport->fake(['*' => static function (PagesRequest $request): MockResponse {
    $page = $request->getContext()?->paginationOptions?->getPage() ?? 1;
    return MockResponse::success(['data' => [$page], 'meta' => ['page' => $page, 'per_page' => 1, 'total' => 6]]);
}]);
$client = new DemoClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
$request = new PagesRequest()->setClient($client);
// Синхронный terminal возвращает готовый агрегат; настройки builder — копии.
$base = $request->paginate()->withPerPage(1);
$range = $base->withConcurrency(3)->range(2, 4);
// Async использует то же правило и типизированный Promise<ResultHandle>.
$promise = $client->sendAsync($request->rules(PaginationRule::all(concurrency: 3)));
$all = $promise->wait()->raw();
$output = ['range' => $range->items(), 'all' => $all->data, 'pages' => count($all->nested)];
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo json_encode($output, JSON_THROW_ON_ERROR) . PHP_EOL;
}
return $output;
