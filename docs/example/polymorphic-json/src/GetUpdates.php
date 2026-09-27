<?php

declare(strict_types=1);

namespace Example\PolymorphicJson;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/updates')]
#[Returns(UpdatesPage::class)]
final class GetUpdates extends AbstractRequest
{
}
