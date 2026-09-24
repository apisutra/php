<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Attributes;

use ApiSutra\Attributes\AttributeContext;

interface AttributeContextHandlerInterface
{
    /**
     * Обработать атрибут в контексте стадии
     */
    public function handle(AttributeContext $context): mixed;
}
