<?php

declare(strict_types=1);

namespace ApiSutra\Localization;

use ApiSutra\Config\LocalizationConfig;

trait LocalizableExceptionTrait
{
    private ?Message $definition = null;
    protected ?self $localizationOrigin = null;
    protected ?LocalizationConfig $messageLocalization = null;

    public function messageDefinition(): ?Message
    {
        return $this->definition;
    }

    protected function initializeMessage(string|Message $message, ?LocalizationConfig $localization): string
    {
        $this->definition = $message instanceof Message ? $message : null;
        $this->messageLocalization = $localization;
        return $message instanceof Message ? $message->render($localization) : $message;
    }

    public function localized(LocalizationConfig $localization): static
    {
        if ($this->definition === null || $this->messageLocalization === $localization) {
            return $this;
        }
        $text = $this->definition->render($localization);
        if ($text === $this->getMessage()) {
            return $this;
        }
        $copy = $this->copyForLocalization();
        $copy->definition = $this->definition;
        $copy->localizationOrigin = $this;
        $copy->messageLocalization = $localization;
        $copy->message = $text;
        return $copy;
    }

    /** Для собственной сигнатуры конструктора подкласс сохраняет свои данные здесь. */
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this->definition ?? $this->getMessage(), $this->getCode(), $this);
    }
}
