<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderB\Enums;

enum ProviderBStatus: string
{
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Failed = 'failed';
    case NotFound = 'not_found';
    case ValidationError = 'validation_error';
    case PaymentRequired = 'payment_required';
    case Suspended = 'suspended';
    case Rejected = 'rejected';
    case Timeout = 'timeout';
    case Canceled = 'canceled';
}
