<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Inventory;

/**
 * Тип response, описываемый записью каталога DTO.
 *
 * - Sync       — sync DTO, объявленный через #[Returns(...)] или #[Returns(type: ...)]
 * - AsyncFinal — финальный DTO для async-цепочки, объявленный через #[ContinuationResult(finalType: ...)]
 * - Download   — request возвращает file response (FileResponse), а не DTO
 */
enum ResponseDtoKind: string
{
    case Sync = 'sync';
    case AsyncFinal = 'async_final';
    case Download = 'download';
}
