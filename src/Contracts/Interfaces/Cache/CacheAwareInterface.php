<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Cache;

use Psr\SimpleCache\CacheInterface;

interface CacheAwareInterface
{
    /**
     * Установить изолированный кеш токенов (возможен локальный backend).
     * Глобальный clear не поддерживается; удалять только собственные логические ключи.
     */
    public function setCache(CacheInterface $cache): void;

    /**
     * Логический ключ токена; pipeline автоматически добавляет область авторизации.
     */
    public function getCacheKey(): string;
}
