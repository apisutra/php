<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\RelativeTypes;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;

#[Get('/node')]
#[Returns(SelfNode::class)]
final class NodeRequest extends AbstractRequest
{
}
