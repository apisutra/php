<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Enums\Request\CredentialsMergeMode;

final readonly class CredentialsScopeConfig
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     * @param array<string, mixed> $form
     */
    public function __construct(
        public array $body = [],
        public array $query = [],
        public array $form = [],
        public ?CredentialsMergeMode $mergeMode = null,
    ) {
    }
}
