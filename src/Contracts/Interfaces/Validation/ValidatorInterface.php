<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Validation;

use ApiSutra\Contracts\Interfaces\DataTransfer\ValidationResult;

interface ValidatorInterface
{
    /**
     * Валидировать объект и вернуть результат
     */
    public static function check(object $value): ValidationResult;
}
