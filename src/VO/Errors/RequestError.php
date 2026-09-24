<?php

declare(strict_types=1);

namespace ApiSutra\VO\Errors;

use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Localization\Message;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\VO\Http\ProviderResponse;

/**
 * Value Object для ошибки выполнения запроса.
 *
 * Представляет ошибку, возникшую при выполнении запроса к API провайдера.
 * Содержит код ошибки, сообщение, ответ провайдера (если есть), вложенные ошибки
 * (для batch/pool операций), контекстную информацию и класс запроса.
 *
 * Используется в:
 * - ErrorPolicy - создаёт ошибки из исключений и неуспешных ответов
 * - ExecutionResultBuilder - добавляет ошибки в результат
 * - BatchExecutor/PoolExecutor - собирает ошибки вложенных запросов
 * - ExecutionResult::$errors - хранит коллекцию ошибок
 * - Paginator - создаёт ошибки при неудачной пагинации
 */
readonly class RequestError
{
    public string $message;
    private ?Message $messageDefinition;


    /**
     * @param ErrorCode $code Код ошибки (NetworkError, ServerError, ValidationError и т.д.)
     * @param string|Message $message Сообщение об ошибке для пользователя
     * @param ProviderResponse|null $response Ответ провайдера (если был получен)
     * @param array<RequestError> $nested Вложенные ошибки (для batch/pool операций)
     * @param array<string, mixed> $context Дополнительный контекст ошибки (параметры, метаданные)
     * @param string|null $requestClass Класс запроса, в котором произошла ошибка (FQCN)
     */
    public function __construct(
        public ErrorCode $code,
        string|Message $message,
        public ?ProviderResponse $response = null,
        public array $nested = [],
        public array $context = [],
        public ?string $requestClass = null,
        ?LocalizationConfig $localization = null,
        ?Message $messageDefinition = null,
    ) {
        $this->messageDefinition = $messageDefinition ?? ($message instanceof Message ? $message : null);
        $this->message = $message instanceof Message ? $message->render($localization) : $message;
    }
    public function messageDefinition(): ?Message
    {
        return $this->messageDefinition;
    }

    public function localized(LocalizationConfig $localization): self
    {
        $message = $this->messageDefinition?->render($localization) ?? $this->message;
        $nested = array_map(static fn (self $error): self => $error->localized($localization), $this->nested);
        if ($message === $this->message && $nested === $this->nested) {
            return $this;
        }
        return new self(
            $this->code,
            $message,
            $this->response,
            $nested,
            $this->context,
            $this->requestClass,
            messageDefinition: $this->messageDefinition,
        );
    }
}
