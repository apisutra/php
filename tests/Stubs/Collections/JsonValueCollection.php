<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Collections;

use ApiSutra\Collections\AbstractTypedCollection;
use ApiSutra\Tests\Stubs\Dto\JsonValue;

/**
 * @extends AbstractTypedCollection<JsonValue>
 */
final readonly class JsonValueCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return JsonValue::class;
    }
}
