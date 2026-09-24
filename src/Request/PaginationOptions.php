<?php

declare(strict_types=1);

namespace ApiSutra\Request;

final readonly class PaginationOptions
{
    public function __construct(
        private ?int $page,
        private bool $pageSet,
        private ?int $limit,
        private bool $limitSet,
        private ?string $cursor,
        private bool $cursorSet,
    ) {
    }

    public static function empty(): self
    {
        return new self(
            page: null,
            pageSet: false,
            limit: null,
            limitSet: false,
            cursor: null,
            cursorSet: false,
        );
    }

    public function withPage(int $page): self
    {
        return new self(
            page: $page,
            pageSet: true,
            limit: $this->limit,
            limitSet: $this->limitSet,
            cursor: $this->cursor,
            cursorSet: $this->cursorSet,
        );
    }

    public function withLimit(int $limit): self
    {
        return new self(
            page: $this->page,
            pageSet: $this->pageSet,
            limit: $limit,
            limitSet: true,
            cursor: $this->cursor,
            cursorSet: $this->cursorSet,
        );
    }

    public function withCursor(?string $cursor): self
    {
        return new self(
            page: $this->page,
            pageSet: $this->pageSet,
            limit: $this->limit,
            limitSet: $this->limitSet,
            cursor: $cursor,
            cursorSet: true,
        );
    }

    public function hasPage(): bool
    {
        return $this->pageSet;
    }

    public function getPage(): ?int
    {
        return $this->page;
    }

    public function hasLimit(): bool
    {
        return $this->limitSet;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function hasCursor(): bool
    {
        return $this->cursorSet;
    }

    public function getCursor(): ?string
    {
        return $this->cursor;
    }
}
