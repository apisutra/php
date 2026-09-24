<?php

declare(strict_types=1);

namespace ApiSutra\DataTransfer;

use ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use ApiSutra\Contracts\Interfaces\Validation\ValidatableInterface;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Traits\ValidatesAttributes;
use ReflectionClass;

abstract readonly class AbstractDto implements DtoInterface, ValidatableInterface
{
    use ValidatesAttributes;

    /**
     * Создание из массива или объекта с toArray()
     */
    #[\Override]
    public static function from(array|object $data): static
    {
        return Hydrator::default()->hydrate($data, static::class);
    }

    /**
     * Удобный конструктор с именованными аргументами.
     */
    public static function make(mixed ...$args): static
    {
        return new static(...$args);
    }

    /**
     * Единый формат сериализации DTO в массив.
     *
     * @return array<string, mixed>
     */
    final public function toArray(): array
    {
        return DtoSerializer::default()->serialize($this);
    }

    /**
     * Клон DTO с переопределениями по именам параметров конструктора.
     */
    public function with(mixed ...$overrides): static
    {
        $reflection = new ReflectionClass($this);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new static(...$overrides);
        }

        $data = $overrides;
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $data)) {
                continue;
            }

            if ($reflection->hasProperty($name)) {
                $property = $reflection->getProperty($name);
                if ($property->isInitialized($this)) {
                    $data[$name] = $property->getValue($this);
                    continue;
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $data[$name] = $parameter->getDefaultValue();
            }
        }

        return new static(...$data);
    }
}
