<?php

declare(strict_types=1);

use ApiSutra\Casts\CastRegistry;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Tests\Stubs\Casts\UppercaseCast;
use ApiSutra\Tests\Stubs\Dto\CastAttributeDto;
use ApiSutra\Tests\Stubs\Dto\CastStringDto;
use ApiSutra\Tests\Stubs\Hydration\ObjectDto;
use ApiSutra\Tests\Stubs\Hydration\PlainValueDto;
use ApiSutra\Tests\Stubs\Hydration\ProviderEnvelopeDto;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Illuminate\\')) {
        throw new RuntimeException('Гидратация загрузила Illuminate: ' . $class);
    }
}, prepend: true);

// Процесс изолирует глобальный registry и проверяет гидратацию без Laravel.
CastRegistry::global()->register('string', new UppercaseCast());
$hydrator = Hydrator::default();
$observations = [
    'registered' => (new DtoSerializer(CastRegistry::global()))->serializeWithPolicy(
        new PlainValueDto('lower'), new DtoSerializationPolicy(), casts: CastRegistry::global(),
    )['value'],
    'plain' => $hydrator->hydrate(['value' => 'lower'], PlainValueDto::class)->value,
    'profile' => CastStringDto::from(['value' => 'lower'])->value,
    'property' => CastAttributeDto::from(['value' => 'lower'])->value,
    'nested' => $hydrator->hydrate(['address' => ['city' => 'Sample']], ObjectDto::class)->address->city,
];
try {
    $hydrator->hydrate(['child' => ['count' => null]], ProviderEnvelopeDto::class);
} catch (HydrationException $error) {
    $observations['providerPath'] = $error->path;
}
$observations['illuminate'] = array_values(array_filter(
    get_declared_classes(),
    static fn (string $class): bool => str_starts_with($class, 'Illuminate\\'),
));

echo json_encode($observations, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
