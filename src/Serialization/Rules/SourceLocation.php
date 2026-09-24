<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

/** @internal Расположение в исходном документе и разрешённое представление для лога. */
final readonly class SourceLocation
{
    /**
     * @param list<int|string> $segments
     * @param list<int|string> $safeSegments
     * @param list<string> $candidates
     * @param list<string> $safeCandidates
     */
    public function __construct(
        public array $segments = [],
        public array $safeSegments = [],
        public SourcePathKind $kind = SourcePathKind::Resolved,
        public array $candidates = [],
        public array $safeCandidates = [],
    ) {
    }

    /** @param list<int|string> $segments */
    public static function pointer(array $segments): string
    {
        return $segments === [] ? '' : '/' . implode('/', array_map(
            static fn (int|string $segment): string => str_replace(['~', '/'], ['~0', '~1'], (string) $segment),
            $segments,
        ));
    }

    /** @param list<int|string> $segments */
    public function descend(array $segments, bool $safe = true, SourcePathKind $kind = SourcePathKind::Resolved): self
    {
        if ($this->kind === SourcePathKind::Boundary || $this->kind === SourcePathKind::Unavailable) {
            return $this;
        }
        return new self(
            [...$this->segments, ...$segments],
            [...$this->safeSegments, ...($safe ? $segments : array_fill(0, count($segments), '*'))],
            $kind,
        );
    }

    public function boundary(): self
    {
        return new self($this->segments, $this->safeSegments, SourcePathKind::Boundary);
    }
}
