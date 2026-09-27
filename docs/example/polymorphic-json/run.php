<?php

declare(strict_types=1);

namespace Example\PolymorphicJson;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use RuntimeException;

require __DIR__ . '/../sdk/bootstrap.php';

$json = file_get_contents(__DIR__ . '/payload.json');
if ($json === false) {
    throw new RuntimeException('Не удалось прочитать пример JSON');
}
$hydration = new HydrationConfig();
$hydrator = Hydrator::forConfig($hydration);

// Тот же Update служит корнем webhook, HTTP-ответом и элементом polling-списка.
$webhook = $hydrator->hydrateJson($json, Update::class);
$transport = new MockTransport();
$transport->preventStrayRequests();
$transport->fake([
    GetUpdate::class => MockResponse::make($json),
    GetUpdates::class => MockResponse::make('{"updates":[' . $json . ']}'),
]);
$client = new DemoClient(new ClientConfig(baseUrl: 'https://example.test', hydration: $hydration), $transport);
$http = $client->send(new GetUpdate())->dataOrFail();
$polling = $client->send(new GetUpdates())->dataOrFail();
if (!$webhook instanceof MessageUpdate || !$polling instanceof UpdatesPage || $http != $webhook || $polling->updates[0] != $webhook) {
    throw new RuntimeException('HTTP, polling и webhook должны давать одинаковые модели');
}

$unknown = $hydrator->hydrateJson('{"update_type":"future_update","feature":true}', Update::class);
if (!$unknown instanceof UnknownUpdate || !$webhook->message->attachments[1] instanceof RawAttachment) {
    throw new RuntimeException('Неизвестные варианты должны сохраняться в fallback DTO');
}

$errors = [];
$invalid = [
    'participants' => str_replace('"participants": {"17": "owner", "28": "reader"}', '"participants": []', $json),
    'payload' => str_replace('"payload": {"url": "https://example.test/article"}', '"payload": []', $json),
    'permissions' => str_replace('"permissions": []', '"permissions": {}', $json),
];
foreach ($invalid as $field => $body) {
    try {
        $hydrator->hydrateJson($body, Update::class);
        throw new RuntimeException('Неверная форма не должна становиться успешным DTO');
    } catch (HydrationException $error) {
        $errors[$field] = ['reason' => $error->reason, 'sourcePath' => $error->sourcePath];
    }
}
$transport->fake([GetUpdates::class => MockResponse::make('{"updates":[' . $invalid['permissions'] . ']}')]);
$failedPolling = $client->send(new GetUpdates())->raw();
$errors['polling_permissions'] = $failedPolling->errors->first()?->context['reason'];
if ($errors['polling_permissions'] !== 'invalid_list_shape') {
    throw new RuntimeException('Polling должен сохранять проверку формы вложенного списка');
}

$result = [
    'same_model' => true,
    'featured' => $webhook->message->featured::class,
    'raw_attachment' => $webhook->message->attachments[1]->raw,
    'unknown_update' => $unknown->raw,
    'errors' => $errors,
];
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
}
return $result;
