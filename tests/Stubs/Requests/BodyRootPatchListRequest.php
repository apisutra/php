<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Patch;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Attributes\Request\Header;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;

#[Patch('/body-root/{id}')]
final class BodyRootPatchListRequest extends AbstractRequest
{
    /**
     * @param array<int, array<string, mixed>> $operations
     */
    public function __construct(
        #[Path('id')]
        public string $id,
        #[Query('dryRun')]
        public bool $dryRun,
        #[Header('X-Mode')]
        public string $mode,
        #[BodyRoot]
        public array $operations,
    ) {}
}
