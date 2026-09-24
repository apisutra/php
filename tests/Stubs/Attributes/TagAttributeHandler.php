<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Attributes;

use ApiSutra\Attributes\AttributeContext;
use ApiSutra\Contracts\Interfaces\Attributes\AttributeContextHandlerInterface;

final class TagAttributeHandler implements AttributeContextHandlerInterface
{
    public function handle(AttributeContext $context): mixed
    {
        $data = is_array($context->data) ? $context->data : [];
        $data[] = $context->attribute->value;

        return $data;
    }
}
