<?php

declare(strict_types=1);

namespace ApiSutra\Versioning;

use ApiSutra\Localization\Message;
use BackedEnum;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Core\AbstractResource;
use ApiSutra\Exceptions\Configuration\UnsupportedVersionException;
use UnitEnum;

/**
 * Технический helper для version‑router ресурсов.
 *
 * Задача трейта — убрать бойлерплейт переключения версий:
 * - выбор версии через enum (`v()`);
 * - роутинг вложенных ресурсов (`resourceByVersion()`);
 * - роутинг запросов (`requestByVersion()`).
 *
 * Трейт не содержит доменной логики ресурса. Бизнес‑методы
 * (`search/create/patch/...`) остаются в конкретном ресурсе.
 */
trait VersionedResourceTrait
{
    /**
     * Переключить текущую версию ресурса через enum.
     */
    public function v(UnitEnum $version): static
    {
        $key = $version instanceof BackedEnum
            ? (string) $version->value
            : $version->name;

        return $this->vRaw($key);
    }

    /**
     * Внутренний адаптер для переключения версии по строковому ключу.
     */
    protected function vRaw(string $version): static
    {
        return $this->recreateWithVersion($version);
    }

    /**
     * @param array<string, class-string<AbstractResource>> $map
     */
    protected function resourceByVersion(array $map, mixed ...$args): AbstractResource
    {
        $this->assertVersionSupported($map);
        $class = $map[$this->currentVersionKey()];

        return $this->resource($class, ...$args);
    }

    /**
     * @param array<string, class-string<AbstractRequest>> $map
     */
    protected function requestByVersion(array $map, mixed ...$args): AbstractRequest
    {
        $this->assertVersionSupported($map);
        $class = $map[$this->currentVersionKey()];

        return $this->request($class, ...$args);
    }

    /**
     * @param array<string, class-string> $map
     */
    protected function assertVersionSupported(array $map): void
    {
        $version = $this->currentVersionKey();
        if (!array_key_exists($version, $map)) {
            throw new UnsupportedVersionException(new Message('versioning.version_is_not_supported', ['version' => $version]));
        }
    }

    /**
     * Текущий ключ версии ресурса (например: v1/v2/v3).
     */
    abstract protected function currentVersionKey(): string;

    /**
     * Вернуть новый экземпляр ресурса с другой версией.
     */
    abstract protected function recreateWithVersion(string $version): static;
}
