<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Plan;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\Enums\Http\QueryArrayFormat;
use ApiSutra\Enums\Request\RequestUnmappedTarget;

/** @internal Только декларация поля: значения запроса и политика клиента сюда не входят. */
final readonly class RequestFieldPlan
{
    public function __construct(
        public string $name,
        public RequestFieldTarget $target,
        public SerializationValuePlan $value,
        public ?string $queryName = null,
        public ?QueryArrayFormat $queryFormat = null,
        public ?bool $queryNullable = null,
        public ?string $bodyNested = null,
        public ?string $partName = null,
        public ?FileFormat $fileFormat = null,
    ) {
    }

    public function route(bool $placeholder, RequestUnmappedTarget $unmapped): RequestFieldTarget
    {
        // BodyRoot/File/Header обрабатывались раньше неявного Path и сохраняют приоритет.
        return match ($this->target) {
            RequestFieldTarget::Skip, RequestFieldTarget::BodyRoot,
            RequestFieldTarget::File, RequestFieldTarget::Header, RequestFieldTarget::Path => $this->target,
            default => $placeholder ? RequestFieldTarget::Path : match ($this->target) {
                RequestFieldTarget::Unmapped => $unmapped === RequestUnmappedTarget::Query
                    ? RequestFieldTarget::Query : RequestFieldTarget::Unmapped,
                default => $this->target,
            },
        };
    }

    public function withCast(?Cast $cast): self
    {
        return new self(
            $this->name,
            $this->target,
            new SerializationValuePlan($this->value->property, $cast),
            $this->queryName,
            $this->queryFormat,
            $this->queryNullable,
            $this->bodyNested,
            $this->partName,
            $this->fileFormat,
        );
    }
}
