<?php

declare(strict_types=1);

use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\Tests\Stubs\Core\PsrNetworkFailure;
use ApiSutra\Tests\Stubs\Core\SequenceHttpClient;
use ApiSutra\Exceptions\Transport\ConnectionException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ApiSutra\Tests\Stubs\Core\TestHttpClient;

describe('HttpTransport', function () {
    it('sendAsync возвращает Promise с ответом', function () {
        $client = new SequenceHttpClient([new Response(200)]);
        $factory = new HttpFactory();
        $transport = new HttpTransport($client, $factory, $factory);

        $prepared = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/example',
        );

        $response = $transport->sendAsync($prepared)->wait();

        expect($response->status)->toBe(200);
        expect($client->calls)->toBe(1);
    });
});

it('нормализует PSR network failure без зависимости от Guzzle HTTP Client', function (): void {
    $failure = new PsrNetworkFailure(new Request('GET', 'https://api.test'));
    $client = new SequenceHttpClient([$failure, $failure]);
    $factory = new HttpFactory();
    $transport = new HttpTransport($client, $factory, $factory);
    $prepared = new PreparedRequest(HttpMethod::GET, 'https://api.test');
    foreach ([false, true] as $async) {
        try {
            $async ? $transport->sendAsync($prepared)->wait() : $transport->send($prepared);
            test()->fail('Ожидалась ошибка транспорта');
        } catch (ConnectionException $exception) {
            expect($exception->getPrevious())->toBe($failure);
        }
    }
    expect($client->calls)->toBe(2);
});
