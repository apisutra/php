<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\InvalidCompositeRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Validation\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;

describe('Pipeline validation order', function () {
    it('не выполняет composite при ошибке валидации', function () {
        $translator = new Translator(new ArrayLoader(), 'en');
        $factory = new ValidationFactory($translator);
        Validator::useFactory($factory);

        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 1]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);

        $request = new InvalidCompositeRequest();
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->isFailed())->toBeTrue();
        expect($transport->getRecorded())->toHaveCount(0);
    });
});
