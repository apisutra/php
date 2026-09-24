<?php

declare(strict_types=1);

namespace ApiSutra\Casts;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Contracts\Interfaces\Casting\SerializationCastInterface;
use ApiSutra\Exceptions\Core\RuntimeException;

final class CastRegistry
{
    private static ?self $global = null;

    /**
     * @var array<string, HydrationCastInterface|SerializationCastInterface|string> Реестр кастов по типам.
     */
    private array $casts = [];

    /**
     * Общий registry для явного использования. Регистрация здесь не меняет DTO::from():
     * гидратация использует отдельный registry профиля DTO и атрибуты свойств.
     */
    public static function global(): self
    {
        return self::$global ??= new self();
    }

    public function get(string $type): HydrationCastInterface|SerializationCastInterface|null
    {
        if (!array_key_exists($type, $this->casts)) {
            return null;
        }

        $cast = $this->casts[$type];
        if ($cast instanceof HydrationCastInterface || $cast instanceof SerializationCastInterface) {
            return $cast;
        }

        if (class_exists($cast)) {
            $instance = new $cast();
            if (!$instance instanceof HydrationCastInterface && !$instance instanceof SerializationCastInterface) {
                throw new RuntimeException(new Message('casts.cast_must_implement_hydrationcastinterface_or_serializationcastinterface', ['cast' => $cast]));
            }
            $this->casts[$type] = $instance;
            return $instance;
        }

        return null;
    }

    /**
     * @param HydrationCastInterface|SerializationCastInterface|class-string<HydrationCastInterface|SerializationCastInterface> $cast Каст или класс каста.
     */
    public function register(string $type, HydrationCastInterface|SerializationCastInterface|string $cast): void
    {
        $this->casts[$type] = $cast;
    }

    public function isEmpty(): bool
    {
        return $this->casts === [];
    }
}
