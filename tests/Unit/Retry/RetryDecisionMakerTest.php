<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Transport\ConnectionException;
use ApiSutra\Pipeline\Error\ErrorPolicy;
use ApiSutra\Pipeline\Transport\RetryDecisionMaker;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;

describe('RetryDecisionMaker', function () {
    it('сохраняет явный обычный retryOn 401 при authRetryOn401', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', authRetryOn401: true, environment: Environment::Testing);
        $decision = new RetryDecisionMaker($config, new ErrorPolicy());

        $request = new SimpleGetRequest('q');
        $response = new ProviderResponse(
            status: 401,
            headers: [],
            body: '',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );
        $retryConfig = new RetryConfig(retryOn: [401]);

        expect($decision->shouldRetry($request, $response, 1, $retryConfig))->toBeTrue();
    });

    it('ретраит по списку статусов', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $decision = new RetryDecisionMaker($config, new ErrorPolicy());

        $request = new SimpleGetRequest('q');
        $response = new ProviderResponse(
            status: 500,
            headers: [],
            body: '',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );
        $retryConfig = new RetryConfig(retryOn: [500]);

        expect($decision->shouldRetry($request, $response, 1, $retryConfig))->toBeTrue();
    });

    it('распознаёт retry исключения', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $decision = new RetryDecisionMaker($config, new ErrorPolicy());

        $retryConfig = new RetryConfig();

        expect($decision->isRetryException(new ConnectionException('fail'), $retryConfig))->toBeTrue();
    });
});
