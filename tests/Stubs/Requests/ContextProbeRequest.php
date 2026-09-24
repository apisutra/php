<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Attributes\ContextProbeAttribute;

#[Get('/context-probe')]
#[ContextProbeAttribute('class-a')]
#[ContextProbeAttribute('class-b')]
final class ContextProbeRequest extends AbstractRequest
{
    #[ContextProbeAttribute('property')]
    public string $payload = 'value';
}
