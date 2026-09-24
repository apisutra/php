<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Localization;

use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Localization\Message;
use Throwable;

interface LocalizableExceptionInterface extends Throwable
{
    public function messageDefinition(): ?Message;
    public function localized(LocalizationConfig $localization): static;
}
