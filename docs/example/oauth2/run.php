<?php

declare(strict_types=1);

use ApiSutra\Auth\OAuth2\AuthorizationAttempt;
use ApiSutra\Auth\OAuth2\AuthorizationCodeFlow;
use ApiSutra\Auth\OAuth2\OAuth2Authenticator;
use ApiSutra\Auth\OAuth2\OAuth2Credential;
use ApiSutra\Auth\OAuth2\OAuth2TokenSet;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\OAuth2Config;
use ApiSutra\Exceptions\Auth\OAuth2Exception;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use Example\OAuth2\Client;
use Example\OAuth2\ResourceRequest;

require __DIR__ . '/../sdk/bootstrap.php';
require_once __DIR__ . '/Client.php';
require_once __DIR__ . '/ResourceRequest.php';

// Полностью локальный пример: endpoint и credentials вымышлены.
$oauth = new OAuth2Config('https://identity.example.test/token', 'example-client', 'fixture-secret', ['reports.read']);
$config = new ClientConfig(baseUrl: 'https://api.example.test');

// Client Credentials: два ресурсных вызова переиспользуют один токен.
$ccTransport = new MockTransport();
$ccTransport->preventStrayRequests();
$ccTransport->fake([
    'https://identity.example.test/token' => MockResponse::success(['access_token' => 'fixture-cc', 'token_type' => 'Bearer']),
    ResourceRequest::class => MockResponse::success(['reports' => []]),
]);
$ccClient = new Client($config->with(auth: OAuth2Authenticator::clientCredentials($oauth)), $ccTransport);
$ccClient->send(new ResourceRequest())->dataOrFail();
$ccClient->sendAsync(new ResourceRequest())->wait()->dataOrFail();

// Authorization Code: после обмена первый ресурсный 401 запускает refresh.
$transport = new MockTransport();
$transport->preventStrayRequests();
$transport->fake([
    'https://identity.example.test/token' => MockResponse::sequence([
        MockResponse::success(['access_token' => 'fixture-access', 'refresh_token' => 'fixture-refresh', 'token_type' => 'Bearer']),
        MockResponse::success(['access_token' => 'fixture-next', 'refresh_token' => 'fixture-next-refresh', 'token_type' => 'Bearer']),
    ]),
    ResourceRequest::class => MockResponse::sequence([
        MockResponse::make(['error' => 'invalid_token'], 401),
        MockResponse::success(['reports' => []]),
    ]),
]);
$exchangeClient = new Client($config, $transport);
$flow = new AuthorizationCodeFlow($oauth, 'https://identity.example.test/authorize', 'https://app.example.test/callback');
$attempt = $flow->begin();
// Приложение сохраняет snapshot в серверной сессии и атомарно погашает его при callback.
$stored = $attempt->export();
$callback = ['state' => $attempt->state, 'code' => 'fixture-code'];
$request = $flow->exchange(AuthorizationAttempt::restore($stored), $callback, 'https://app.example.test/callback');
$tokens = $exchangeClient->sendAsync($request)->wait()->dataOrFail();
assert($tokens instanceof OAuth2TokenSet);
$saved = $tokens->export(); // Первую пару приложение сохраняет явно, до создания credential.
$credential = new OAuth2Credential(OAuth2TokenSet::restore($saved), 'connection-example', static function (OAuth2TokenSet $next) use (&$saved): void {
    $saved = $next->export(); // В приложении: успешная запись либо исключение.
});
$client = new Client($config->with(auth: OAuth2Authenticator::authorizationCode($oauth, $credential)), $transport);
$resource = $client->send(new ResourceRequest())->dataOrFail();

// Проверка callback бросает напрямую, ещё до отправки запроса и создания промиса.
$callbackFailure = null;
try {
    $flow->exchange($attempt, ['state' => 'wrong-state', 'code' => 'fixture-code'], 'https://app.example.test/callback');
} catch (OAuth2Exception $exception) {
    $callbackFailure = $exception->reason->value; // В приложении: отклонить callback, не повторять обмен.
}

$output = [
    'resource' => $resource,
    'cc_http_calls' => count($ccTransport->getRecorded()),
    'code_http_calls' => count($transport->getRecorded()),
    'rotation_saved' => $saved['refreshToken'] === 'fixture-next-refresh',
    'callback_failure' => $callbackFailure,
];

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo json_encode($output, JSON_THROW_ON_ERROR) . PHP_EOL;
}
return $output;
