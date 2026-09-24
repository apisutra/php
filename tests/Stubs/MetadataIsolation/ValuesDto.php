<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Casts\IntegerCast;
use ApiSutra\Enums\Configuration\Environment;

final readonly class ValuesDto
{
    /** @param array<string, list<Environment>> $values */
    public function __construct(
        #[From('record_id')]
        #[Cast(IntegerCast::class)]
        public int $id = 7,
        public Environment $environment = Environment::Production,
        public array $values = ['env' => [Environment::Testing]],
    ) {
    }
}
