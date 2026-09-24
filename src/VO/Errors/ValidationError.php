<?php

declare(strict_types=1);

namespace ApiSutra\VO\Errors;

/**
 * Value Object для ошибки валидации.
 *
 * Представляет ошибку валидации поля запроса или ответа.
 * Содержит информацию о поле, правиле валидации, сообщении об ошибке
 * и исходном значении поля.
 *
 * Используется в:
 * - Validator::validate() - создаётся при неудачной валидации поля
 * - ValidationException - содержит массив ошибок валидации
 * - ExecutionResult::$validationErrors - хранит ошибки валидации запроса
 * - ValidatableInterface - определяет контракт валидации
 */
readonly class ValidationError
{
    /**
     * @param string $field Имя поля с ошибкой (например, 'email', 'data.user.age')
     * @param string $rule Правило валидации, которое не прошло (например, 'required', 'email', 'min:3')
     * @param string $message Сообщение об ошибке для пользователя
     * @param mixed $input Исходное значение поля, которое не прошло валидацию
     */
    public function __construct(
        public string $field,
        public string $rule,
        public string $message,
        public mixed $input = null,
    ) {
    }
}
