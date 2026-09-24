<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Http;

/** Отсутствие доказательства отправки не означает доказанное отсутствие отправки. */
enum TransmissionState: string
{
    case NotSent = 'not_sent';
    case Unknown = 'unknown';
}
