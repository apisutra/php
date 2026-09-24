<?php

declare(strict_types=1);

use ApiSutra\Support\ContainerProviderRegistry;
use ApiSutra\Support\NullContainerProvider;
use Illuminate\Container\Container;

it('не обнаруживает установленный Illuminate Container автоматически', function (): void {
    $previous = Container::getInstance();
    Container::setInstance(new Container());
    try {
        expect(ContainerProviderRegistry::resolve())->toBeInstanceOf(NullContainerProvider::class);
    } finally {
        Container::setInstance($previous);
    }
});

it('применяет новый default после Null и после другого default без кеша выбора', function (): void {
    $null = ContainerProviderRegistry::resolve();
    $a = new NullContainerProvider();
    $b = new NullContainerProvider();
    ContainerProviderRegistry::setDefault($a);
    expect(ContainerProviderRegistry::resolve())->toBe($a)->not->toBe($null);
    ContainerProviderRegistry::setDefault($b);
    expect(ContainerProviderRegistry::resolve())->toBe($b);
    ContainerProviderRegistry::reset();
    expect(ContainerProviderRegistry::resolve())->toBe($null);
});

it('сохраняет приоритет override, explicit и default включая явный Null', function (): void {
    $default = new NullContainerProvider();
    $explicit = new NullContainerProvider();
    $override = new NullContainerProvider();
    ContainerProviderRegistry::set($explicit);
    ContainerProviderRegistry::setDefault($default);
    expect(ContainerProviderRegistry::resolve())->toBe($explicit)
        ->and(ContainerProviderRegistry::resolve($override))->toBe($override)
        ->and(ContainerProviderRegistry::resolve())->toBe($explicit);
    ContainerProviderRegistry::setDefault(new NullContainerProvider());
    expect(ContainerProviderRegistry::resolve())->toBe($explicit);
    ContainerProviderRegistry::reset();
    expect(ContainerProviderRegistry::resolve())->not->toBe($explicit)->not->toBe($default);
});

it('сбрасывает регистрации, не меняя provider вложенной активной области', function (): void {
    $fallback = ContainerProviderRegistry::resolve();
    $outer = new NullContainerProvider();
    $inner = new NullContainerProvider();
    ContainerProviderRegistry::set(new NullContainerProvider());
    ContainerProviderRegistry::setDefaultResolver(static fn () => new NullContainerProvider());
    ContainerProviderRegistry::withProvider($outer, static function () use ($inner, $outer): void {
        ContainerProviderRegistry::withProvider($inner, static function () use ($inner): void {
            ContainerProviderRegistry::reset();
            expect(ContainerProviderRegistry::resolve())->toBe($inner);
        });
        expect(ContainerProviderRegistry::resolve())->toBe($outer);
    });
    expect(ContainerProviderRegistry::resolve())->toBe($fallback);
});

it('reset между приостановками не меняет контейнер уже начатой Fiber', function (): void {
    $captured = new NullContainerProvider();
    $next = new NullContainerProvider();
    $fiber = new Fiber(static fn () => ContainerProviderRegistry::withProvider($captured, static function () {
        Fiber::suspend(ContainerProviderRegistry::resolve());
        return ContainerProviderRegistry::resolve();
    }));
    expect($fiber->start())->toBe($captured);
    ContainerProviderRegistry::reset();
    ContainerProviderRegistry::setDefault($next);
    $fiber->resume();
    expect($fiber->getReturn())->toBe($captured)->and(ContainerProviderRegistry::resolve())->toBe($next);
});
