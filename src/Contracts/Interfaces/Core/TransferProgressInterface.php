<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

/**
 * Поддержка TransportOptions::transferProgress транспортом или HTTP-адаптером.
 * Счётчики передаются из фактической передачи; fake принимает опцию без уведомлений.
 */
interface TransferProgressInterface
{
    public function assertSupportsTransferProgress(): void;
}
