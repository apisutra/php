<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Validation;

use ApiSutra\VO\Errors\ValidationError;

/**
 * Расширяемая preflight-валидация запроса до сериализации и HTTP.
 *
 * Используется для проверок, которые не покрываются #[Validate]:
 * - валидация файлов (размер, формат, целостность);
 * - кросс-полевая логика;
 * - domain-specific правила.
 *
 * Вызывается после attribute-валидации, перед RequestContractValidator.
 * Ошибки объединяются и подаются в buildValidationFailure.
 *
 * @see docs/guides/validation.md
 */
interface CustomValidatableRequestInterface
{
    /**
     * Preflight-валидация до сериализации.
     *
     * Не должен бросать исключения — только возвращать список ошибок.
     *
     * @return array<ValidationError> Пустой массив = успех.
     */
    public function validateCustom(): array;
}
