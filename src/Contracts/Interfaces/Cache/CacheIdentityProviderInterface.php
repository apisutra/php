<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Cache;

use ApiSutra\VO\Http\PreparedRequest;

/** Контекст доступа к кешу; получение значения не должно выполнять I/O. */
interface CacheIdentityProviderInterface
{
    /**
     * Стабильная непрозрачная identity в рамках провайдера; null запрещает HTTP-кеширование.
     * Для token store используется вариант без request; null оставляет только локальное хранение.
     * Без request возвращается identity подключения/группы для очистки.
     * С request учитывается фактический контекст после auth/hooks для custom key.
     * Разные права доступа/tenant должны давать разные значения. Не возвращать секреты.
     */
    public function getCacheIdentity(?PreparedRequest $request = null): ?string;
}
