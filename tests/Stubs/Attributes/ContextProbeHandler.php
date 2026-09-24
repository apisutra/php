<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Attributes;

use ApiSutra\Attributes\AttributeContext;
use ApiSutra\Contracts\Interfaces\Attributes\AttributeContextHandlerInterface;

final class ContextProbeHandler implements AttributeContextHandlerInterface
{
    /**
     * @var array<int, AttributeContext>
     */
    public static array $contexts = [];

    public static function reset(): void
    {
        self::$contexts = [];
    }

    public function handle(AttributeContext $context): mixed
    {
        self::$contexts[] = $context;

        return $context->data;
    }
}
