<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\Http\TransmissionState;
use ApiSutra\Exceptions\Transport\ConnectionException;
use ApiSutra\Exceptions\Transport\InvalidRequestException;
use ApiSutra\Exceptions\Transport\TimeoutException;
use ApiSutra\Tests\Stubs\Core\PsrNetworkFailure;
use ApiSutra\Tests\Stubs\Core\SequenceHttpClient;
use ApiSutra\Tests\Stubs\Requests\RetryAllowedRequest;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Transport\TransportExceptionNormalizer;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('нормализует подтверждённые сетевые коды без потери причины', function (int $errno, string $expected): void {
    $original = new RequestException('fixture', new Request('GET', 'https://fixture.test'), handlerContext: ['errno' => $errno]);
    $failure = TransportExceptionNormalizer::normalize($original);
    expect($failure)->toBeInstanceOf($expected)->and($failure->getPrevious())->toBe($original)
        ->and($failure->transmissionState)->toBe(TransmissionState::Unknown)
        ->and(TransportExceptionNormalizer::normalize($failure))->toBe($failure);
})->with([[55, ConnectionException::class], [56, ConnectionException::class], [52, ConnectionException::class], [28, TimeoutException::class], [3, InvalidRequestException::class]]);

it('не угадывает отправку из PSR network ошибки или DNS errno', function (): void {
    $request = new Request('GET', 'https://fixture.test');
    foreach ([new PsrNetworkFailure($request), new ConnectException('fixture', $request, handlerContext: ['errno' => 6])] as $original) {
        $failure = TransportExceptionNormalizer::normalize($original);
        expect($failure)->toBeInstanceOf(ConnectionException::class)
            ->and($failure->transmissionState)->toBe(TransmissionState::Unknown);
    }
});

it('55/56 следуют существующей безопасности GET и POST', function (int $errno, string $kind, int $calls): void {
    $original = new RequestException('fixture', new Request('POST', 'https://fixture.test'), handlerContext: ['errno' => $errno]);
    $http = new SequenceHttpClient([$original, new Response(200, ['Content-Type' => 'application/json'], '{}')]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(attempts: 2, baseDelay: 0, jitter: false)), new HttpTransport($http, $factory, $factory));
    $request = match ($kind) {
        'get' => new RetryPolicyRequest(),
        'post' => new RetryPolicyRequest(HttpMethod::POST),
        'safe-post' => new RetryAllowedRequest(HttpMethod::POST),
    };
    $result = $client->send($request)->raw();
    expect($http->calls)->toBe($calls)->and($result->isSuccess())->toBe($calls === 2);
    if ($calls === 1) {
        expect($result->errors->first()->code->value)->toBe('connection_failed')
            ->and($result->errors->first()->context['transmissionState'])->toBe('unknown');
    }
})->with([[55, 'get', 2], [56, 'get', 2], [55, 'post', 1], [56, 'post', 1], [55, 'safe-post', 2]]);

it('неопределённость ранней попытки не теряется при последующем not_sent', function (TransmissionState $first): void {
    $transport = new MockTransport();
    $calls = 0;
    $transport->fake(['*' => static function () use (&$calls, $first): never {
        $calls++;
        throw new ConnectionException('fixture', transmissionState: $calls === 1 ? $first : TransmissionState::NotSent);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(attempts: 2, baseDelay: 0, jitter: false)), $transport);
    $result = $client->send(new RetryPolicyRequest())->raw();
    expect($calls)->toBe(2)->and($result->exception->transmissionState)->toBe(TransmissionState::NotSent)
        ->and($result->errors->first()->context['transmissionState'])->toBe($first->value);
})->with([TransmissionState::Unknown, TransmissionState::NotSent]);
