<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Validation\ValidationException;
use ApiSutra\Support\ContainerProviderRegistry;
use ApiSutra\Support\NullContainerProvider;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Validation\ScopedValidationDto;
use ApiSutra\Tests\Stubs\Validation\ScopedValidationProvider;
use ApiSutra\Tests\Stubs\Validation\ScopedValidationRequest;
use ApiSutra\Tests\Stubs\Validation\ValidationOrderRequest;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Errors\ValidationError;
use ApiSutra\VO\Validation\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

function scopedValidationFactory(bool $accept, string $message = 'fixture-rejected', ?Closure $onCheck = null): Factory
{
    $factory = new Factory(new Translator(new ArrayLoader(), 'en'));
    $factory->extend('fixture_rule', static function () use ($accept, $onCheck): bool {
        $onCheck?->__invoke();
        return $accept;
    }, $message);
    return $factory;
}

it('применяет фабрику клиента поверх общей и доставляет ошибки данных', function (bool $async, bool $static): void {
    $global = scopedValidationFactory(true);
    if ($static) {
        Validator::useFactory($global);
    } else {
        ContainerProviderRegistry::set(new ScopedValidationProvider($global));
    }
    $provider = new ScopedValidationProvider(scopedValidationFactory(false, 'client-rejected'));
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $request = (new ScopedValidationRequest())->setClient(new TestClient(
        new ClientConfig(baseUrl: 'https://fixture.test', containerProvider: $provider), $transport,
    ));
    expect($request->isValid())->toBeFalse()->and($request->errors()[0]->message)->toBe('client-rejected')
        ->and(fn () => $request->validate())->toThrow(ValidationException::class);
    $handle = $async ? $request->sendAsync()->wait() : $request->send();
    expect($handle->raw()->errors->first()->code->value)->toBe('validation_failed')
        ->and($handle->raw()->validationErrors[0]->input)->toBe('fixture-value')
        ->and($handle->resolved()->error()->sdkCode->value)->toBe('validation_failed')
        ->and(fn () => $handle->dataOrFail())->toThrow(ValidationException::class)
        ->and($transport->getRecorded())->toHaveCount(0)
        ->and($provider->factoryCalls)->toBe(4);
})->with([false, true])->with([false, true]);

it('не заменяет отсутствующую или неверную фабрику клиента общей', function (string $kind): void {
    Validator::useFactory(scopedValidationFactory(true));
    $factory = match ($kind) {
        'missing' => null,
        'wrong' => (object) ['secret' => 'fixture-secret'],
        'throwing' => new RuntimeException('fixture-secret'),
    };
    $provider = new ScopedValidationProvider($factory);
    $transport = new MockTransport();
    $request = (new ScopedValidationRequest())->setClient(new TestClient(
        new ClientConfig(baseUrl: 'https://fixture.test', containerProvider: $provider), $transport,
    ));
    $result = $request->send()->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')
        ->and($result->validationErrors)->toBe([])->and($result->response)->toBeNull()
        ->and($result->exception)->toBeInstanceOf(ConfigurationException::class)
        ->and($result->exception->getMessage())->not->toContain('fixture-secret', 'useFactory')
        ->and($transport->getRecorded())->toHaveCount(0);
})->with(['missing', 'wrong', 'throwing']);

it('отказывает без движка только при объявленных правилах', function (): void {
    expect(fn () => (new ScopedValidationDto())->isValid())->toThrow(ConfigurationException::class)
        ->and(fn () => (new ScopedValidationDto())->errors())->toThrow(ConfigurationException::class)
        ->and(fn () => (new ScopedValidationDto())->validate())->toThrow(ConfigurationException::class);
    $provider = new ScopedValidationProvider(new RuntimeException('Не должен вызываться'));
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $request = (new CacheProbeRequest())->setClient(new TestClient(
        new ClientConfig(baseUrl: 'https://fixture.test', containerProvider: $provider), $transport,
    ));
    expect($request->isValid())->toBeTrue()->and($request->send()->raw()->isSuccess())->toBeTrue()
        ->and($provider->factoryCalls)->toBe(0)->and($transport->getRecorded())->toHaveCount(1);
});

it('изолирует A B A и не запоминает клиента в DTO', function (): void {
    Validator::useFactory(scopedValidationFactory(false, 'global'));
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $requests = [];
    foreach ([true, false] as $accept) {
        $requests[] = (new ScopedValidationRequest())->setClient(new TestClient(new ClientConfig(
            baseUrl: 'https://fixture.test', containerProvider: new ScopedValidationProvider(scopedValidationFactory($accept)),
        ), $transport));
    }
    foreach ([0, 1, 0] as $index) {
        expect($requests[$index]->send()->raw()->isSuccess())->toBe($index === 0);
    }
    expect($transport->getRecorded())->toHaveCount(2)
        ->and((new ScopedValidationDto())->errors()[0]->message)->toBe('global');
});

it('поддерживает явный provider для DTO поверх bootstrap', function (): void {
    Validator::useFactory(scopedValidationFactory(false));
    $provider = new ScopedValidationProvider(scopedValidationFactory(true));
    $dto = new ScopedValidationDto();
    expect(Validator::check($dto, provider: $provider)->passed())->toBeTrue();
    Validator::validateOrThrow($dto, provider: $provider);
    expect($provider->factoryCalls)->toBe(2)->and($dto->isValid())->toBeFalse();
});

it('сохраняет bootstrap до явного reset и затем использует общий provider', function (): void {
    ContainerProviderRegistry::set(new ScopedValidationProvider(scopedValidationFactory(false)));
    Validator::useFactory(scopedValidationFactory(true));
    expect(Validator::check(new ScopedValidationDto())->passed())->toBeTrue();
    Validator::resetFactory();
    expect(Validator::check(new ScopedValidationDto())->failed())->toBeTrue();
    ContainerProviderRegistry::set(new NullContainerProvider());
    expect(fn () => Validator::check(new ScopedValidationDto()))->toThrow(ConfigurationException::class);
});

it('использует фабрику явно зарегистрированного default provider', function (): void {
    ContainerProviderRegistry::reset();
    ContainerProviderRegistry::setDefault(new ScopedValidationProvider(scopedValidationFactory(false, 'default')));
    expect((new ScopedValidationDto())->errors()[0]->message)->toBe('default');
});

it('берёт контекст выполняющего клиента независимо от привязки запроса', function (bool $explicit): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $boundProvider = new ScopedValidationProvider(scopedValidationFactory(false, 'bound'));
    $bound = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', containerProvider: $boundProvider), $transport);
    Validator::useFactory(scopedValidationFactory(true));
    $executing = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test',
        containerProvider: $explicit ? new ScopedValidationProvider(scopedValidationFactory(true)) : null,
    ), $transport);
    $request = (new ScopedValidationRequest())->setClient($bound);
    expect($executing->send($request)->raw()->isSuccess())->toBeTrue()
        ->and($boundProvider->factoryCalls)->toBe(0)
        ->and($request->isValid())->toBeFalse()
        ->and(Validator::check($request, provider: new ScopedValidationProvider(scopedValidationFactory(true)))->passed())->toBeTrue();
})->with([false, true]);

it('доставляет недоступность валидатора через result и throwOnErrors', function (bool $async, bool $throw): void {
    $transport = new MockTransport();
    $request = (new ScopedValidationRequest())->setClient(new TestClient(
        new ClientConfig(baseUrl: 'https://fixture.test', throwOnErrors: $throw), $transport,
    ));
    if ($throw) {
        expect(static function () use ($request, $async): void {
            ($async ? $request->sendAsync()->wait() : $request->send())->raw();
        })->toThrow(ConfigurationException::class);
    } else {
        $handle = $async ? $request->sendAsync()->wait() : $request->send();
        expect($handle->raw()->errors->first()->code->value)->toBe('configuration_error')
            ->and($handle->resolved()->error()->sdkCode->value)->toBe('configuration_error')
            ->and(fn () => $handle->dataOrFail())->toThrow(ConfigurationException::class);
    }
    expect($transport->getRecorded())->toHaveCount(0);
})->with([false, true])->with([false, true]);

it('вложенная валидация другого клиента не подменяет bootstrap или внешнего клиента', function (): void {
    Validator::useFactory(scopedValidationFactory(false, 'bootstrap'));
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $inner = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', containerProvider: new ScopedValidationProvider(scopedValidationFactory(false, 'inner')),
    ), $transport);
    $calls = 0;
    $outerFactory = scopedValidationFactory(true, onCheck: function () use ($inner, &$calls): void {
        $calls++;
        expect($inner->send(new ScopedValidationRequest())->raw()->validationErrors[0]->message)->toBe('inner')
            ->and((new ScopedValidationDto())->errors()[0]->message)->toBe('bootstrap');
    });
    $outer = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', containerProvider: new ScopedValidationProvider($outerFactory),
    ), $transport);
    for ($index = 0; $index < 2; $index++) {
        expect($outer->send(new ScopedValidationRequest())->raw()->isSuccess())->toBeTrue();
    }
    expect($calls)->toBe(2)->and($transport->getRecorded())->toHaveCount(2)
        ->and((new ScopedValidationDto())->errors()[0]->message)->toBe('bootstrap');
});

it('ручной непривязанный запрос не требует разрешения клиента', function (): void {
    Validator::useFactory(scopedValidationFactory(true));
    $request = new ScopedValidationRequest();
    expect($request->validate())->toBe($request)->and($request->isValid())->toBeTrue()
        ->and($request->errors())->toBe([])->and($request->hasClient())->toBeFalse();
});

it('сохраняет пользовательское исключение validation rule', function (): void {
    $exception = new DomainException('fixture-business-error');
    $provider = new ScopedValidationProvider(scopedValidationFactory(true, onCheck: static function () use ($exception): void {
        throw $exception;
    }));
    try {
        Validator::check(new ScopedValidationDto(), provider: $provider);
        test()->fail('Ошибка правила потеряна');
    } catch (DomainException $caught) {
        expect($caught)->toBe($exception);
    }
});


it('сохраняет порядок проверок и объединение сообщений', function (bool $available): void {
    if ($available) {
        Validator::useFactory(scopedValidationFactory(true));
    }
    $request = new ValidationOrderRequest();
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
    $result = $client->send($request)->raw();
    expect($result->errors->first()->code->value)->toBe($available ? 'validation_failed' : 'configuration_error')
        ->and($request->customCalls)->toBe($available ? 1 : 0)
        ->and($request->compositeCalls)->toBe(0)
        ->and($transport->getRecorded())->toHaveCount(0);
    if ($available) {
        expect(array_map(static fn (ValidationError $error): string => $error->message, $result->validationErrors))
            ->toBe(['attribute Email', 'class name', 'custom error'])
            ->and($result->validationErrors[0]->field)->toBe('email')
            ->and($result->validationErrors[0]->rule)->toBe('required')
            ->and($result->validationErrors[0]->input)->toBe('');
    } else {
        expect($result->validationErrors)->toBe([]);
    }
})->with([false, true]);
