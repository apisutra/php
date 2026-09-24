<?php

declare(strict_types=1);

namespace ApiSutra\VO\Validation;

use ApiSutra\Contracts\Interfaces\DataTransfer\ValidationResult as ValidationResultContract;
use ApiSutra\VO\Errors\ValidationError;

/**
 * Результат валидации объекта.
 *
 * Содержит статус валидации (прошла/не прошла) и список ошибок валидации.
 * Реализует контракт ValidationResultContract для унифицированного API.
 *
 * Используется в:
 * - Validator::check() - возвращает результат проверки объекта
 * - Validator::validateOrThrow() - проверяет результат и бросает исключение
 * - ValidatableInterface - контракт для объектов с валидацией
 */
final class ValidationResult implements ValidationResultContract
{
    /**
     * @param bool $passed Флаг успешной валидации (true = ошибок нет)
     * @param array<ValidationError> $errors Список ошибок валидации (пустой если passed=true)
     */
    public function __construct(
        private bool $passed,
        private array $errors,
    ) {
    }

    /**
     * Проверяет, прошла ли валидация успешно.
     *
     * @return bool true если ошибок нет
     */
    #[\Override]
    public function passed(): bool
    {
        return $this->passed;
    }

    /**
     * Проверяет, провалилась ли валидация.
     *
     * @return bool true если есть ошибки
     */
    #[\Override]
    public function failed(): bool
    {
        return !$this->passed;
    }

    /**
     * Возвращает список ошибок валидации.
     *
     * @return array<ValidationError>
     */
    #[\Override]
    public function errors(): array
    {
        return $this->errors;
    }
}
