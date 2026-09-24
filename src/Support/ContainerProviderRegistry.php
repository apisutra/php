<?php

declare(strict_types=1);

namespace ApiSutra\Support;

use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use Closure;
use Fiber;
use WeakMap;

/**
 * Явный и установленный интеграцией провайдеры общего контейнера.
 */
final class ContainerProviderRegistry
{
    private static ?ContainerProviderInterface $explicit = null;
    private static ?ContainerProviderInterface $default = null;
    private static ?NullContainerProvider $null = null;
    /** @var Closure(): ContainerProviderInterface|null */
    private static ?Closure $defaultResolver = null;
    private static ?ContainerProviderInterface $main = null;
    /** @var WeakMap<Fiber, ContainerProviderInterface>|null */
    private static ?WeakMap $scoped = null;

    public static function set(ContainerProviderInterface $provider): void
    {
        self::$explicit = $provider;
    }

    public static function setDefault(ContainerProviderInterface $provider): void
    {
        self::$default = $provider;
        self::$defaultResolver = null;
    }

    /** Разрешение интеграции выполняется на входе, а не между приостановками.
     * @param Closure(): ContainerProviderInterface $resolver
     */
    public static function setDefaultResolver(Closure $resolver): void
    {
        self::$default = null;
        self::$defaultResolver = $resolver;
    }

    /** @template T
     * @param Closure(): T $operation
     * @return T
     */
    public static function withProvider(ContainerProviderInterface $provider, Closure $operation): mixed
    {
        $fiber = Fiber::getCurrent();
        $previous = self::current();
        self::put($fiber, $provider);
        try {
            return $operation();
        } finally {
            self::put($fiber, $previous);
        }
    }

    /** Сбрасывает регистрации; provider активной области остаётся до её выхода через finally. */
    public static function reset(): void
    {
        self::$explicit = null;
        self::$default = null;
        self::$defaultResolver = null;
    }

    public static function resolve(?ContainerProviderInterface $override = null): ContainerProviderInterface
    {
        return $override ?? self::current() ?? self::$explicit
            ?? (self::$defaultResolver !== null ? (self::$defaultResolver)() : self::$default)
            ?? (self::$null ??= new NullContainerProvider());
    }

    private static function current(): ?ContainerProviderInterface
    {
        $fiber = Fiber::getCurrent();
        return $fiber === null ? self::$main : (self::$scoped[$fiber] ?? null);
    }

    private static function put(?Fiber $fiber, ?ContainerProviderInterface $provider): void
    {
        if ($fiber === null) {
            self::$main = $provider;
        } elseif ($provider !== null) {
            self::$scoped ??= new WeakMap();
            self::$scoped[$fiber] = $provider;
        } elseif (self::$scoped !== null) {
            unset(self::$scoped[$fiber]);
        }
    }
}
