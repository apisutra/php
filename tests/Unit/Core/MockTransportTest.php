<?php

declare(strict_types=1);

use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Testing\UnmockedRequestException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\PreparedRequest;

describe('MockTransport', function () {
    it('блокирует незамоканные запросы при preventStrayRequests', function () {
        $transport = new MockTransport();
        $transport->preventStrayRequests();

        $prepared = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/unknown',
        );

        expect(fn () => $transport->send($prepared))
            ->toThrow(UnmockedRequestException::class);
    });

    it('возвращает замоканный ответ и записывает запрос', function () {
        $transport = new MockTransport();
        $transport->fake([
            '*' => MockResponse::success(['ok' => true]),
        ]);

        $prepared = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/example',
        );

        $response = $transport->send($prepared);

        expect($response->status)->toBe(200);
        expect($transport->getRecorded())->toHaveCount(1);
    });

    it('sendAsync возвращает замоканный ответ и записывает запрос', function () {
        $transport = new MockTransport();
        $transport->fake([
            '*' => MockResponse::success(['ok' => true]),
        ]);

        $prepared = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/example',
        );

        $promise = $transport->sendAsync($prepared);
        $response = $promise->wait();

        expect($response->status)->toBe(200);
        expect($transport->getRecorded())->toHaveCount(1);
    });

    it('sendAsync отклоняется при preventStrayRequests', function () {
        $transport = new MockTransport();
        $transport->preventStrayRequests();

        $prepared = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/unknown',
        );

        $promise = $transport->sendAsync($prepared);

        expect(fn () => $promise->wait())
            ->toThrow(UnmockedRequestException::class);
    });
});
