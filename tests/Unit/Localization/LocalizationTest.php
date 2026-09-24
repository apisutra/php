<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Localization\Message;
use ApiSutra\Localization\MessageCatalog;
use ApiSutra\Localization\MessageFormatter;
use ApiSutra\Resolver\ClientRegistry;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Localization\ParentDto;
use ApiSutra\Tests\Stubs\Localization\Request;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Errors\DefaultClientErrorMapper;
use ApiSutra\VO\Errors\RequestError;

it('имеет полные каталоги с одинаковыми параметрами', function (): void {
    $en = MessageCatalog::all('en');
    $ru = MessageCatalog::all('ru');
    expect(array_keys($ru))->toBe(array_keys($en));
    foreach ($en as $key => $template) {
        expect(MessageFormatter::parameters($ru[$key]), $key)->toBe(MessageFormatter::parameters($template));
        $parameters = array_fill_keys(MessageFormatter::parameters($template), 'fixture');
        expect((new Message($key, $parameters))->render())->not->toBe($key);
        expect((new Message($key, $parameters))->render(new LocalizationConfig('ru')))->not->toBe($key);
    }
});

it('выбирает язык, fallback и безопасно подставляет значения', function (): void {
    $message = new Message('configuration.request_namespaces_missing', ['class' => '{other}']);
    expect($message->render())->toBe("No request namespaces found for client '{other}'")
        ->and($message->render(new LocalizationConfig('ru_RU')))->toBe("Не найдены namespace запросов клиента '{other}'")
        ->and($message->render(new LocalizationConfig('de')))->toBe($message->render())
        ->and((new Message('sdk.unknown'))->render())->toBe('sdk.unknown');
});

it('поддерживает каталог SDK и отбрасывает неполное переопределение', function (): void {
    $localization = new LocalizationConfig('ru', messages: [
        'ru' => ['sdk.limit' => 'Лимит: {limit}', 'configuration.request_namespaces_missing' => 'Потерян параметр'],
        'en' => ['sdk.limit' => 'Limit: {limit}'],
    ]);
    expect((new Message('sdk.limit', ['limit' => 5]))->render($localization))->toBe('Лимит: 5')
        ->and((new Message('configuration.request_namespaces_missing', ['class' => 'Demo']))->render($localization))
        ->toBe("Не найдены namespace запросов клиента 'Demo'");
    expect(fn () => new LocalizationConfig('../ru'))->toThrow(ConfigurationException::class);
});

it('не изменяет исходное исключение и сохраняет буквальные сторонние сообщения', function (): void {
    $source = new ConfigurationException(new Message('configuration.request_namespaces_missing', ['class' => 'Demo']), 12);
    $ru = $source->localized(new LocalizationConfig('ru'));
    expect($ru)->not->toBe($source)->toBeInstanceOf(ConfigurationException::class)
        ->and($ru->getCode())->toBe(12)->and($ru->getPrevious())->toBe($source)
        ->and($source->getMessage())->toBe("No request namespaces found for client 'Demo'")
        ->and($ru->getMessage())->toBe("Не найдены namespace запросов клиента 'Demo'");
    $foreign = new ConfigurationException("No request namespaces found for client 'Demo'");
    expect($foreign->localized(new LocalizationConfig('ru')))->toBe($foreign);
});

it('сохраняет язык в конфигурации и использует его до создания клиента', function (): void {
    $localization = new LocalizationConfig('ru');
    $config = new ClientConfig('https://example.test', localization: $localization);
    expect($config->with(timeout: 7)->localization)->toBe($localization);
    expect(fn () => $config->with(baseUrl: ''))->toThrow(ConfigurationException::class, 'ClientConfig.baseUrl не должен быть пустым');
});

it('нормализует строку языка и сохраняет тип конфигурации', function (): void {
    $config = new ClientConfig('https://example.test', localization: 'ru_RU');
    expect($config->localization)->toBeInstanceOf(LocalizationConfig::class)
        ->and($config->localization->locale)->toBe('ru-ru')
        ->and($config->with(timeout: 7)->localization)->toBe($config->localization);
    expect(fn () => new ClientConfig('', localization: 'ru'))
        ->toThrow(ConfigurationException::class, 'ClientConfig.baseUrl не должен быть пустым');
});

it('заменяет блок при строковом override без скрытого переноса каталога', function (): void {
    $localization = new LocalizationConfig('ru', messages: ['ru' => ['fixture.message' => 'Сообщение SDK']]);
    $config = new ClientConfig('https://example.test', localization: $localization);
    $copy = $config->with(localization: 'en');
    expect($copy->localization->locale)->toBe('en')->and($copy->localization->messages)->toBe([])
        ->and($config->localization)->toBe($localization)
        ->and($config->localization->locale)->toBe('ru');
});

it('отклоняет неверную строку языка через ConfigurationException', function (string $locale): void {
    expect(fn () => new ClientConfig('https://example.test', localization: $locale))
        ->toThrow(ConfigurationException::class, 'Invalid locale identifier');
})->with(['', '../ru']);

it('изолирует standalone гидраторы с общим metadata cache', function (): void {
    $cache = new AttributeMetadataCache();
    foreach (['ru', 'en', 'ru'] as $language) {
        $hydrator = new Hydrator(new CastRegistry(), $cache, localization: new LocalizationConfig($language));
        try {
            $hydrator->hydrate(['child' => ['count' => []]], ParentDto::class);
            test()->fail('Ожидалась ошибка');
        } catch (HydrationException $exception) {
            expect($exception->path)->toBe('child.count')
                ->and($exception->reason)->toBe('invalid_field_type')
                ->and($exception->getMessage())->toStartWith($language === 'ru' ? 'Некорректные данные' : 'Invalid data');
            $withPath = $exception->prependPath('root');
            expect($withPath->getMessage())->toStartWith($language === 'ru' ? 'Некорректные данные' : 'Invalid data');
        }
    }
});

it('применяет язык клиента к результату, DX и выбрасываемому исключению', function (bool $async, bool $throw): void {
    foreach (['ru', 'en', 'ru'] as $language) {
        $transport = new MockTransport();
        $transport->preventStrayRequests();
        $transport->fake([Request::class => MockResponse::success(['child' => ['count' => []]])]);
        $client = new TestClient(new ClientConfig('https://example.test', throwOnErrors: $throw, localization: new LocalizationConfig($language)), $transport);
        $prefix = $language === 'ru' ? 'Некорректные данные' : 'Invalid data';
        if ($throw) {
            expect(fn () => ($async ? $client->sendAsync(new Request())->wait() : $client->send(new Request()))->raw())->toThrow(HydrationException::class, $prefix);
        } else {
            $handle = $async ? $client->sendAsync(new Request())->wait() : $client->send(new Request());
            $result = $handle->raw();
            expect($result->errors->first()->message)->toStartWith($prefix)
                ->and($result->exception->getMessage())->toStartWith($prefix)
                ->and($handle->resolved()->errorMessage())->toStartWith($prefix)
                ->and($result->errors->first()->context['path'])->toBe('child.count');
        }
    }
})->with([[false, false], [true, false], [false, true], [true, true]]);

it('локализует реестр независимо от конфигурации клиентов', function (): void {
    $registry = new ClientRegistry(new LocalizationConfig('ru'));
    expect(fn () => $registry->resolve('Demo\\Request'))->toThrow(ConfigurationException::class, "Клиент для запроса 'Demo\\Request' не зарегистрирован");
});

it('сохраняет descriptor в DX и не раскрывает параметры при сериализации', function (): void {
    $error = new RequestError(ErrorCode::ConfigurationError, new Message('configuration.request_namespaces_missing', ['class' => 'Demo']));
    $ru = $error->localized(new LocalizationConfig('ru'));
    $mapped = (new DefaultClientErrorMapper())->map($ru);
    expect($mapped->message)->toBe($ru->message)
        ->and($mapped->messageDefinition()?->key)->toBe('configuration.request_namespaces_missing')
        ->and(json_encode($mapped))->not->toContain('parameters', 'messageDefinition')
        ->and($mapped->localized(new LocalizationConfig())->message)->toBe($error->message);
    $result = new ExecutionResult(null, ResultStatus::FAILED, new ErrorCollection([$error, $error]));
    expect($result->localized(new LocalizationConfig('ru'))->message())->toStartWith('Несколько ошибок: 2.')
        ->and($result->message())->toStartWith('Multiple errors: 2.');
});

it('оставляет сообщение провайдера на его исходном языке', function (): void {
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake([Request::class => MockResponse::make(['message' => 'Ошибка внешнего API'], 400)]);
    $client = new TestClient(new ClientConfig('https://example.test'), $transport);
    expect($client->send(new Request())->resolved()->errorMessage())->toBe('Ошибка внешнего API')
        ->and(ErrorCode::HydrationError->title(new LocalizationConfig('ru')))->toBe('Ошибка гидратации');
});

it('переформатирует вложенные ошибки ручного результата независимо от его locale', function (): void {
    $locale = new LocalizationConfig('ru');
    $error = new RequestError(ErrorCode::ExecutionError, new Message('errors.execution_error'));
    $inner = new ExecutionResult(null, ResultStatus::FAILED, new ErrorCollection([$error]), localization: $locale);
    $outer = new ExecutionResult(null, ResultStatus::FAILED, new ErrorCollection([]), nested: [$inner], localization: $locale);
    $copy = $outer->localized($locale);
    expect($copy->nested[0]->message())->toBe('Ошибка исполнения')
        ->and($inner->message())->toBe('Execution error')
        ->and($copy->localized($locale))->toBe($copy);
});
