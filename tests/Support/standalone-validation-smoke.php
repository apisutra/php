<?php

declare(strict_types=1);

// Запуск: php tests/Support/standalone-validation-smoke.php /path/to/no-dev-checkout
use ApiSutra\Config\ClientConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Support\ContainerProviderRegistry;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use ApiSutra\Tests\Stubs\Requests\CustomValidatableRequestStub;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Validation\ScopedValidationDto;
use ApiSutra\Tests\Stubs\Validation\ScopedValidationRequest;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Errors\ValidationError;
use ApiSutra\VO\Validation\Validator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory;

$checkout = $argv[1] ?? '';
if (!is_file($checkout . '/vendor/autoload.php')) {
    throw new RuntimeException('Укажите checkout с production dependencies (--no-dev) и текущими src');
}
require $checkout . '/vendor/autoload.php';
$stubs = [
    'TestClient',
    'Dto/SimpleResponseDto',
    'Requests/CacheProbeRequest',
    'Requests/CustomValidatableRequestStub',
    'Validation/ScopedValidationRequest',
    'Validation/ScopedValidationDto',
];
foreach ($stubs as $stub) {
    require __DIR__ . '/../Stubs/' . $stub . '.php';
}
if (interface_exists(Factory::class) || class_exists(Container::class)) {
    throw new RuntimeException('В checkout присутствует Illuminate');
}
ContainerProviderRegistry::reset();
Validator::resetFactory();
foreach ([false, true] as $async) {
    foreach (['attributes' => new ScopedValidationRequest(), 'no_attributes' => new CacheProbeRequest(), 'custom' => new CustomValidatableRequestStub()] as $case => $request) {
        if ($request instanceof CustomValidatableRequestStub) {
            $request->customValidationErrors = [new ValidationError('value', 'fixture_rule', 'fixture-rejected', null)];
        }
        $transport = new MockTransport();
        $transport->fake(['*' => MockResponse::success(['ok' => true])]);
        $request->setClient(new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport));
        $handle = $async ? $request->sendAsync()->wait() : $request->send();
        $result = $handle->raw();
        $expectedCode = match ($case) {
            'attributes' => 'configuration_error',
            'custom' => 'validation_failed',
            default => null,
        };
        if (
            $result->errors->first()?->code->value !== $expectedCode
            || count($transport->getRecorded()) !== ($case === 'no_attributes' ? 1 : 0)
            || $result->isSuccess() !== ($case === 'no_attributes')
        ) {
            throw new RuntimeException('Нарушен standalone контракт валидации: ' . $case);
        }
        if ($case === 'attributes') {
            if ($handle->resolved()->error()->sdkCode->value !== 'configuration_error') {
                throw new RuntimeException('Потеряна ошибка в resolved');
            }
            try {
                $handle->dataOrFail();
                throw new RuntimeException('Недоступная валидация не вызвала исключение');
            } catch (ConfigurationException) {
                // Ожидаемый отказ до HTTP.
            }
        }
    }
}
foreach (['validate', 'isValid', 'errors'] as $method) {
    try {
        (new ScopedValidationDto())->{$method}();
        throw new RuntimeException('DTO пропустил недоступную проверку');
    } catch (ConfigurationException) {
        // Ошибка настройки не является успешной проверкой данных.
    }
}
echo "Standalone validation: attributes требуют движок, no-rules/custom работают без Illuminate; sync/promise/DTO проверены.\n";
