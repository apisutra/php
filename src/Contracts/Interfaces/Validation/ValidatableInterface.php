<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Validation;

use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\VO\Errors\ValidationError;

interface ValidatableInterface
{
    /**
     * Валидировать по правилам #[Validate].
     * @throws ConfigurationException Если объявленные проверки недоступны.
     */
    public function validate(): static;

    /**
     * Вернуть результат проверки данных; недоступность проверок является ошибкой настройки.
     * @throws ConfigurationException Если объявленные проверки недоступны.
     */
    public function isValid(): bool;

    /**
     * Получить ошибки валидации
     * @return array<ValidationError>
     * @throws ConfigurationException Если объявленные проверки недоступны.
     */
    public function errors(): array;
}
