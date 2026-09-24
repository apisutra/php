<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Core;

use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Contracts\Interfaces\Localization\LocalizableExceptionInterface;
use ApiSutra\Localization\LocalizableExceptionTrait;
use ApiSutra\Localization\Message;
use RuntimeException as BaseRuntimeException;
use Throwable;

class RuntimeException extends BaseRuntimeException implements LocalizableExceptionInterface
{
    use LocalizableExceptionTrait;

    public function __construct(string|Message $message = '', int $code = 0, ?Throwable $previous = null, ?LocalizationConfig $localization = null)
    {
        parent::__construct($this->initializeMessage($message, $localization), $code, $previous);
    }
}
