<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/event')]
#[Returns(Event::class, unwrap: 'data')]
final class UnwrapEventRequest extends AbstractRequest
{
}
