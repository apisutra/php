<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\DtoMapping;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;
use Override;

#[Get('/custom-hydration')]
final class HydratorRequest extends AbstractRequest
{
    public function __construct(
        private string|false|null $hydrator = null,
        private string $dtoClass = RecordDto::class,
        private ?string $unwrap = null,
    ) {}

    #[Override]
    public function getResponseType(): ?string
    {
        return $this->dtoClass;
    }

    #[Override]
    public function getReturnsAttribute(): ?Returns
    {
        return new Returns($this->dtoClass, unwrap: $this->unwrap, mismatchMessage: 'Unexpected record.', hydrator: $this->hydrator);
    }
}
