<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Extensions;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Extensions\ExtensionContext;

interface ExtensionInterface
{
    /**
     * Уникальное имя расширения
     */
    public function getName(): string;

    /**
     * Phase 1: декларация компонентов
     */
    public function register(ExtensionContext $context): void;

    /**
     * Phase 2: инициализация
     */
    public function boot(ClientConfig $config): void;

    /**
     * Проверка зависимостей
     */
    public function checkDependencies(): void;

    /**
     * Статус после boot
     */
    public function isEnabled(): bool;
}
