<?php

declare(strict_types=1);

namespace ApiSutra\VO\Errors;

use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Localization\Message;
use ApiSutra\Enums\Errors\ErrorCode;

/**
 * Ошибка для клиентского ответа.
 */
final readonly class ClientError
{
    public string $message;
    private ?Message $messageDefinition;


    /**
     * @param array<string, mixed> $context
     * @param array<ClientError> $nested
     */
    public function __construct(
        public ?string $providerCode,
        public ErrorCode $sdkCode,
        public ?string $clientCode,
        public ?string $appCode,
        string|Message $message,
        public array $context = [],
        public array $nested = [],
        public ?string $requestClass = null,
        ?LocalizationConfig $localization = null,
        ?Message $messageDefinition = null,
    ) {
        $this->messageDefinition = $messageDefinition ?? ($message instanceof Message ? $message : null);
        $this->message = $message instanceof Message ? $message->render($localization) : $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_code' => $this->providerCode,
            'sdk_code' => $this->sdkCode->value,
            'client_code' => $this->clientCode,
            'app_code' => $this->appCode,
            'message' => $this->message,
            'context' => $this->context,
            'nested' => array_map(
                static fn (ClientError $error): array => $error->toArray(),
                $this->nested,
            ),
            'request_class' => $this->requestClass,
        ];
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
            $this->providerCode,
            $this->sdkCode,
            $this->clientCode,
            $this->appCode,
            $message,
            $this->context,
            $nested,
            $this->requestClass,
            messageDefinition: $this->messageDefinition,
        );
    }
}
