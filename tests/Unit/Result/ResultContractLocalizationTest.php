<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\Exceptions\Configuration\ExceptionFactoryException;
use ApiSutra\Exceptions\Serialization\ResponseTypeMismatchException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\ConstructorOwned\CompositeRequest;
use ApiSutra\Tests\Stubs\ResultContract\DefaultRequest;
use ApiSutra\Tests\Stubs\ResultContract\ProviderFailure;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Tests\Stubs\ResultContract\ValueExtension;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Audit\PipelineEvent;

it('передаёт фабрике локализованную ошибку с завершённой трассировкой', function (bool $async, bool $throw): void {
    $factory = new RecordingFactory();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 1])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.example.test',
        extensions: [new ValueExtension('unexpected')],
        resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory),
        localization: 'ru',
        throwOnErrors: $throw,
    ), $transport);

    expect(fn () => ($async ? $client->sendAsync(new DefaultRequest())->wait() : $client->send(new DefaultRequest()))->dataOrFail())->toThrow(ProviderFailure::class);
    expect($factory->results)->toHaveCount(1);
    $result = $factory->results[0];
    expect($result->exception)->toBeInstanceOf(ResponseTypeMismatchException::class)
        ->and($result->exception->getMessage())->toStartWith('Некорректный результат ')
        ->and($factory->messages[0])->toBe($result->errors->first()->message)
        ->and($result->exceptionFactory)->toBe($factory)
        ->and($result->trace->executionId)->not->toBeEmpty()
        ->and(array_column($result->audit, 'stage'))->toContain(PipelineStage::Started, PipelineStage::Failed)
        ->and(array_count_values(array_map(static fn (PipelineEvent $event): string => $event->stage->value, $result->audit))[PipelineStage::Failed->value])->toBe(1)
        ->and($transport->getRecorded())->toHaveCount(1);

    $english = $result->localized(new LocalizationConfig('en'));
    expect($english->exception)->toBeInstanceOf(ResponseTypeMismatchException::class)
        ->and($english->exception->getMessage())->toStartWith('Invalid result of ')
        ->and($english->errors->first()->message)->toBe($english->exception->getMessage())
        ->and($english->exception->context())->toBe($result->exception->context())
        ->and($english->trace)->toBe($result->trace)
        ->and($english->exceptionFactory)->toBe($factory)
        ->and($result->exception->getMessage())->toStartWith('Некорректный результат ');
})->with([[false, false], [false, true], [true, false], [true, true]]);

it('локализует сбой фабрики без потери результата и исходной причины', function (): void {
    $cause = new RuntimeException('SDK factory failure');
    $factory = new RecordingFactory(failure: $cause);
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 1])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.example.test',
        extensions: [new ValueExtension('unexpected')],
        resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory),
        localization: 'ru',
    ), $transport);
    $result = $client->send(new DefaultRequest())->raw();
    try {
        $result->throw();
        test()->fail('Фабрика должна завершиться ошибкой');
    } catch (ExceptionFactoryException $failure) {
        expect($failure->getMessage())->toBe('Не удалось сформировать исключение SDK')
            ->and($failure->getPrevious())->toBe($cause)
            ->and($failure->result)->toBe($result)
            ->and($factory->results)->toHaveCount(1);
        $english = $failure->localized(new LocalizationConfig('en'));
        expect($english->getMessage())->toBe('Failed to create the SDK exception')
            ->and($english->result)->toBe($result)
            ->and($english->getPrevious())->toBe($cause);
    }
});

it('локализует нарушение Returns у composite вместе с ошибкой результата', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('null')]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.example.test', localization: 'ru'), $transport);
    $result = $client->send((new CompositeRequest())->setClient($client))->raw();
    expect($result->exception)->toBeInstanceOf(ResponseTypeMismatchException::class)
        ->and($result->errors->first()->message)->toStartWith('Некорректный результат ')
        ->and($result->errors->first()->message)->toBe($result->exception->getMessage());
});
