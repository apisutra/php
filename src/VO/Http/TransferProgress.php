<?php

declare(strict_types=1);

namespace ApiSutra\VO\Http;

use ApiSutra\Diagnostics\ExecutionTrace;

/** Счётчики HTTP-передачи в байтах; не подтверждают обработку запроса сервером. */
final readonly class TransferProgress
{
    public function __construct(
        public ExecutionTrace $trace,
        public int $attempt,
        public int $uploaded,
        public ?int $uploadTotal,
        public int $downloaded,
        public ?int $downloadTotal,
    ) {
    }
}
