<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Collections;

use ApiSutra\Collections\AbstractTypedCollection;
use ApiSutra\VO\Files\Base64File;

/**
 * @extends AbstractTypedCollection<Base64File>
 */
final readonly class Base64FileCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return Base64File::class;
    }
}
