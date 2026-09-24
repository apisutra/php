<?php

declare(strict_types=1);

namespace Example\DtoHydrator;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Throwable;

require __DIR__ . '/../sdk/bootstrap.php';

$transport = new MockTransport();
$transport->fake([
    GetUser::class => MockResponse::success(['id' => 7, 'name' => ' Ada ', 'address' => ['city' => 'London']]),
    ListUsers::class => MockResponse::sequence([
        MockResponse::success(['data' => [['id' => 8, 'name' => ' Grace ']], 'meta' => ['page' => 1, 'has_more' => true]]),
        MockResponse::make(['error' => 'unavailable page'], status: 400),
    ]),
]);
$client = new Client(new ClientConfig(
    baseUrl: 'https://example.test',
    throwOnErrors: false,
    hydration: new HydrationConfig(hydrator: new UserHydrator(new UserFactory())),
), $transport);
$user = $client->send(new GetUser())->dataOrFail();
$seen = [];
$failed = false;
try {
    foreach (new ListUsers()->setClient($client)->paginate()->items() as $item) {
        $seen[] = $item->name;
    }
} catch (Throwable) {
    // Уже обработанные элементы остаются у приложения. Отказ не означает конец списка.
    $failed = true;
}
return [
    'user' => $user->toArray(),
    'seen' => $seen,
    'failed_page' => $failed,
    'http_calls' => count($transport->getRecorded()),
];
