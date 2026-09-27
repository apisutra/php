<?php

declare(strict_types=1);

namespace Example\Records\Webhook;

use Example\Records\Resources\Records\Get\GetRecordResponseDto;

// Учебный webhook содержит тот же data, что HTTP-ответ: правила DTO не дублируются.
final readonly class RecordPayload
{
    public function __construct(public GetRecordResponseDto $data)
    {
    }
}
