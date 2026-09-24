<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\DataTransfer;

interface DtoInterface
{
    /**
     * Фабричный метод создания из массива или объекта
     * Объект должен иметь метод toArray()
     */
    public static function from(array|object $data): static;
}
