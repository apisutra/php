<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Container;

/**
 * Провайдер контейнера приложения (опционально).
 */
interface ContainerProviderInterface
{
    /**
     * Проверить наличие зависимости в контейнере.
     */
    public function bound(string $id): bool;

    /**
     * Получить зависимость из контейнера.
     */
    public function make(string $id): ?object;

    /**
     * Базовый путь приложения.
     */
    public function basePath(): ?string;

    /**
     * Окружение приложения.
     */
    public function environment(): ?string;

    /**
     * Флаг debug.
     */
    public function isDebug(): ?bool;

    /**
     * Фабрика валидатора (если доступна).
     */
    public function validatorFactory(): ?object;
}
