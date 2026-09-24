<?php

declare(strict_types=1);

use ApiSutra\Metadata\MetadataCatalog;
use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\RuleSetCompiler;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Tests\Stubs\AttributeHydration\ReceiverInput;
use ApiSutra\Tests\Stubs\MappingMetadata\PairA;
use ApiSutra\Tests\Stubs\MappingMetadata\PairB;
use ApiSutra\Tests\Stubs\MappingMetadata\RootValid;
use ApiSutra\Tests\Stubs\MappingMetadata\RootInvalid;
use ApiSutra\Tests\Stubs\MappingMetadata\LazyValid;
use ApiSutra\Tests\Stubs\MappingMetadata\LazyInvalid;
use ApiSutra\Tests\Stubs\MetadataIsolation\ValuesDto;

it('публикует рекурсивную группу и сохраняет memoization при отключённом metadata cache', function (bool $enabled): void {
    $compiler = new RuleSetCompiler(metadata: new MetadataCatalog($enabled));
    $first = $compiler->forClass(PairA::class);
    $second = $compiler->forClass(PairB::class);
    expect($compiler->forClass(PairA::class))->toBe($first)
        ->and($compiler->forClass(PairB::class))->toBe($second);
    $dto = Hydrator::default()->hydrate(['next' => ['next' => ['next' => null]]], PairA::class);
    expect($dto->next->next)->toBeInstanceOf(PairA::class);
})->with([false, true]);

it('сохраняет прежний рубеж сброса и не кеширует ошибки', function (bool $enabled): void {
    $compiler = new RuleSetCompiler(metadata: new MetadataCatalog($enabled));
    $ready = $compiler->forClass(ValuesDto::class);
    for ($i = 0; $i < 2; $i++) {
        expect(fn () => $compiler->forClass(AbstractDto::class))->toThrow(ConfigurationException::class);
        expect($compiler->forClass(ValuesDto::class))->toBe($ready);
    }
    for ($i = 0; $i < 2; $i++) {
        expect(fn () => $compiler->forClass(ReceiverInput::class))->toThrow(ConfigurationException::class);
        $next = $compiler->forClass(ValuesDto::class);
        expect($next)->not->toBe($ready);
        $ready = $next;
    }
})->with([false, true]);

it('отделяет правила разных владельцев при общем каталоге', function (): void {
    $metadata = new MetadataCatalog();
    $legacy = new RuleSetCompiler(metadata: $metadata);
    $strict = new RuleSetCompiler(new HydrationConfig(policy: new RulePolicy(scalars: ScalarPolicy::Strict)), $metadata);
    expect($legacy->forClass(PairA::class)->policy->scalars)->toBeNull()
        ->and($strict->forClass(PairA::class)->policy->scalars)->toBe(ScalarPolicy::Strict)
        ->and($legacy->forClass(PairA::class))->not->toBe($strict->forClass(PairA::class));
});

it('закрывает от внешнего входа всю группу во время autoload и освобождает её после исхода', function (string $root, string $lazy, bool $valid): void {
    $compiler = new RuleSetCompiler();
    $ready = $compiler->forClass(ValuesDto::class);
    // Отложенные классы лежат вне PSR-4 каталога и загружаются только после приостановки.
    $loader = static function (string $class) use ($lazy, $valid): void {
        if ($class === $lazy) {
            Fiber::suspend('paused');
            require dirname(__DIR__, 2) . '/Fixtures/MappingMetadata/Lazy' . ($valid ? 'Valid' : 'Invalid') . '.php';
        }
    };
    spl_autoload_register($loader, true, true);
    try {
        $fiber = new Fiber(fn () => $compiler->forClass($root));
        expect($fiber->start())->toBe('paused');
        foreach ([$root, PairA::class, PairB::class, AbstractDto::class] as $class) {
            expect(fn () => $compiler->forClass($class))->toThrow(ConfigurationException::class, 'Reentrant access');
            expect(fn () => $compiler->receiverFor($class))->toThrow(ConfigurationException::class, 'Reentrant access');
        }
        expect($compiler->forClass(ValuesDto::class))->toBe($ready);
        if ($valid) {
            $fiber->resume();
            expect($compiler->forClass($root))->toBe($fiber->getReturn())
                ->and($compiler->forClass(PairA::class)->reflection->getName())->toBe(PairA::class)
                ->and($compiler->forClass(ValuesDto::class))->toBe($ready);
        } else {
            expect(fn () => $fiber->resume())->toThrow(ConfigurationException::class, 'Receiver requires');
            expect(fn () => $compiler->forClass($root))->toThrow(ConfigurationException::class, 'Receiver requires');
            expect($compiler->forClass(ValuesDto::class))->not->toBe($ready);
            expect($compiler->forClass(PairA::class)->reflection->getName())->toBe(PairA::class);
        }
    } finally {
        spl_autoload_unregister($loader);
    }
})->with([
    [RootValid::class, LazyValid::class, true],
    [RootInvalid::class, LazyInvalid::class, false],
]);

it('доставляет исходный Throwable до рубежа и разрешает повтор после внешнего reentry', function (): void {
    $compiler = new RuleSetCompiler();
    $ready = $compiler->forClass(ValuesDto::class);
    $exception = new RuntimeException('synthetic autoload failure');
    $missing = 'ApiSutraMissingMetadataRoot';
    $loader = static function (string $class) use ($missing, $exception): void {
        if ($class === $missing) {
            Fiber::suspend();
            throw $exception;
        }
    };
    spl_autoload_register($loader, true, true);
    try {
        $fiber = new Fiber(fn () => $compiler->forClass($missing));
        $fiber->start();
        expect(fn () => $compiler->forClass($missing))->toThrow(ConfigurationException::class, 'Reentrant access');
        try {
            $fiber->resume();
            test()->fail('Должно доставляться исключение autoload');
        } catch (RuntimeException $error) {
            expect($error)->toBe($exception);
        }
        expect($compiler->forClass(ValuesDto::class))->toBe($ready);
    } finally {
        spl_autoload_unregister($loader);
    }
    expect(fn () => $compiler->forClass($missing))->toThrow(ConfigurationException::class, 'not found');
});
