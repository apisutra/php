<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Contracts\Interfaces\Errors\ExecutionExceptionFactoryInterface;

/** Настройки сообщений контракта и выдачи конечных исключений клиента. */
final readonly class ResultExceptionConfig
{
    public function __construct(
        public ?string $mismatchMessage = null,
        public ?ExecutionExceptionFactoryInterface $exceptionFactory = null,
    ) {
    }
}
