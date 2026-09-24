<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Continuation\ContinuationOutcome;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Continuation\ContinuationAwaitException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Localization\Message;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Errors\ValidationError;
use ApiSutra\Tests\Stubs\Requests\CustomValidatableRequestStub;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Serialization\Rules\SourceLocation;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Tests\Stubs\Dto\ContinuationFinalDto;
use ApiSutra\Tests\Stubs\Localization\MemoryLogger;
use ApiSutra\Tests\Stubs\Localization\Request;
use ApiSutra\Tests\Stubs\Localization\ThrowingTransport;
use ApiSutra\Tests\Stubs\Requests\BodyRootConflictIgnoreRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

it('изолирует локализованные представления одного исключения при чередовании Fiber', function (): void {
    $source = new ConfigurationException(new Message('configuration.request_namespaces_missing', ['class' => 'Demo']));
    $transport = new ThrowingTransport($source, suspend: true);
    $en = new TestClient(new ClientConfig('https://example.test'), $transport);
    $ru = new TestClient(new ClientConfig('https://example.test', localization: new LocalizationConfig('ru')), $transport);
    $first = new Fiber(fn () => $ru->send(new Request())->raw());
    $second = new Fiber(fn () => $en->send(new Request())->raw());
    $first->start();
    $second->start();
    $second->resume();
    $first->resume();
    expect($first->getReturn()->message())->toStartWith('Не найдены namespace')
        ->and($second->getReturn()->message())->toStartWith('No request namespaces')
        ->and($first->getReturn()->exception)->not->toBe($source)
        ->and($source->getMessage())->toStartWith('No request namespaces');
});

it('сохраняет sourcePath и безопасный logContext после локализации и дополнения пути', function (): void {
    $source = HydrationException::invalidValue('invalid_field_type', 'int', 'string', 'id')
        ->withSource(new SourceLocation(['private-source'], ['*'], SourcePathKind::Resolved));
    $ru = $source->localized(new LocalizationConfig('ru'))->prependPath('child');
    expect($ru->context()['path'])->toBe('child.id')
        ->and($ru->sourcePath)->toBe('/private-source')
        ->and($ru->logContext()['sourcePath'])->toBe('/*')
        ->and($source->path)->toBe('id')
        ->and($ru->getMessage())->toStartWith('Некорректные данные в child.id');
});

it('локализует ошибки wire-сериализации с общим кешем без запоминания языка', function (): void {
    $cache = new AttributeMetadataCache();
    foreach (['ru', 'en'] as $locale) {
        $serializer = new Serializer(new CastRegistry(), $cache, localization: new LocalizationConfig($locale));
        expect(fn () => $serializer->serialize(new BodyRootConflictIgnoreRequest('value')))
            ->toThrow(ConfigurationException::class, $locale === 'ru' ? 'BodyRoot не может' : 'BodyRoot cannot');
    }
});

it('сохраняет причину, lastResult и контекст final_hydration_failed', function (): void {
    $client = new TestClient(new ClientConfig('https://example.test', localization: new LocalizationConfig('ru')), new MockTransport());
    $last = new ExecutionResult(null, ResultStatus::SUCCESS, new ErrorCollection([]));
    $outcome = new ContinuationOutcome(null, ['value' => []], 'data', $last, 3);
    try {
        $client->continuation()->hydrateOutcome($outcome, ContinuationFinalDto::class);
        test()->fail('Ожидалась ошибка');
    } catch (ContinuationAwaitException $exception) {
        expect($exception->reason)->toBe('final_hydration_failed')
            ->and($exception->attempts)->toBe(3)->and($exception->lastResult)->toBe($last)
            ->and($exception->getMessage())->toBe('Не удалось преобразовать готовый результат ожидания')
            ->and($exception->context()['hydration']['path'])->toBe('data.value')
            ->and($exception->getPrevious())->toBeInstanceOf(ContinuationAwaitException::class);
    }
});

it('локализует запись лога и сохраняет очистку секретов', function (): void {
    $logger = new MemoryLogger();
    $config = new ClientConfig('https://example.test', logger: $logger, localization: new LocalizationConfig('ru'));
    (new AuditLogger($config))->log('error', new Message('pipeline.request_ended_with_an_exception'), ['token' => 'synthetic-secret']);
    expect($logger->records[0]['message'])->toBe('Запрос завершился исключением')
        ->and(json_encode($logger->records))->not->toContain('synthetic-secret', 'parameters');
});

it('сохраняет буквальный Throwable пользователя в обоих языках', function (): void {
    $source = new RuntimeException('Ошибка пользовательского обработчика');
    foreach (['en', 'ru'] as $locale) {
        $client = new TestClient(new ClientConfig('https://example.test', localization: new LocalizationConfig($locale)), new ThrowingTransport($source));
        $result = $client->send(new Request())->raw();
        expect($result->exception)->toBe($source)->and($result->message())->toBe($source->getMessage());
    }
});

it('оставляет сообщение стороннего валидатора при переводе обёртки', function (): void {
    $field = new ValidationError('email', 'required', 'Собственный текст валидатора');
    foreach (['en' => 'Request validation failed', 'ru' => 'Ошибка валидации запроса'] as $locale => $expected) {
        $request = new CustomValidatableRequestStub();
        $request->customValidationErrors = [$field];
        $client = new TestClient(new ClientConfig('https://example.test', localization: new LocalizationConfig($locale)), new MockTransport());
        $result = $client->send($request)->raw();
        expect($result->validationErrors)->toBe([$field])->and($result->message())->toBe($expected)
            ->and($field->message)->toBe('Собственный текст валидатора');
    }
});
