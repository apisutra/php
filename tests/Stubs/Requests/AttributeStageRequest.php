<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Attributes\TagAttribute;

#[Get('/attribute-stage')]
#[TagAttribute('class')]
final class AttributeStageRequest extends AbstractRequest
{
    public function __construct(
        #[TagAttribute('property')]
        public string $payload = 'value',
    ) {}
}
