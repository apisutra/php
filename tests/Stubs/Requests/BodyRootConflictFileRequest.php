<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Patch;
use ApiSutra\Attributes\Request\BodyRoot;
use ApiSutra\Attributes\Request\File;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\VO\Files\FileInput;

#[Patch('/body-root/conflict/file')]
final class BodyRootConflictFileRequest extends AbstractRequest
{
    /**
     * @param array<int, array<string, mixed>> $operations
     */
    public function __construct(
        #[BodyRoot]
        public array $operations,
        #[File('file')]
        public FileInput $file,
    ) {}
}
