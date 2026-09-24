<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Enums\Request\CredentialsMergeMode;

final readonly class CredentialsEnrichmentConfig
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     * @param array<string, mixed> $form
     * @param array<string, CredentialsScopeConfig> $scopes
     * @param array<int, string> $secretKeys
     */
    public function __construct(
        public bool $enabled = true,
        public CredentialsMergeMode $mergeMode = CredentialsMergeMode::FillMissing,
        public array $body = [],
        public array $query = [],
        public array $form = [],
        public array $scopes = [],
        public array $secretKeys = [],
    ) {
    }
}
