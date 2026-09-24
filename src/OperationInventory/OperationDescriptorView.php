<?php

declare(strict_types=1);

namespace ApiSutra\OperationInventory;

use ApiSutra\Attributes\Request\OperationDescriptor;
use ApiSutra\Enums\Http\HttpMethod;

final readonly class OperationDescriptorView
{
    /**
     * @param array<int, string>      $sdkCallPaths
     * @param array<int, string>|null $resourcePath
     */
    public function __construct(
        public string $requestClass,
        public ?HttpMethod $httpMethod,
        public ?string $endpoint,
        public ?string $responseType,
        public ?OperationDescriptor $operationDescriptor,
        public bool $hasDownload,
        public bool $hasNoAuth,
        public bool $skipCredentialsEnrichment,
        public array $sdkCallPaths = [],
        public ?string $continuationFinalType = null,
        public ?string $continuationUnwrap = null,
        public ?string $pollRequestClass = null,
        public ?string $returnsUnwrap = null,
        public ?array $resourcePath = null,
        public ?string $resourceLabel = null,
    ) {
    }

    public function title(): ?string
    {
        return $this->operationDescriptor?->title;
    }

    public function description(): ?string
    {
        return $this->operationDescriptor?->description;
    }

    public function note(): ?string
    {
        return $this->operationDescriptor?->note;
    }

    public function isAsync(): bool
    {
        return $this->continuationFinalType !== null;
    }
}
