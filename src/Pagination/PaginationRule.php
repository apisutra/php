<?php

declare(strict_types=1);

namespace ApiSutra\Pagination;

use ApiSutra\Localization\Message;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Enums\Pagination\PaginationMode;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

/**
 * Правила выполнения пагинации.
 */
final readonly class PaginationRule
{
    private function __construct(
        public PaginationMode $mode,
        public ?int $pages = null,
        public ?int $from = null,
        public ?int $to = null,
        public FailStrategy $failStrategy = FailStrategy::FailAll,
        public int $concurrency = 1,
    ) {
        if ($concurrency < 1) {
            throw new ConfigurationException(new Message('pagination.concurrency_must_be_positive'));
        }
    }

    public static function single(): self
    {
        return new self(mode: PaginationMode::Single);
    }

    public static function all(FailStrategy $failStrategy = FailStrategy::FailAll, int $concurrency = 1): self
    {
        return new self(mode: PaginationMode::All, failStrategy: $failStrategy, concurrency: $concurrency);
    }

    public static function pages(int $count, FailStrategy $failStrategy = FailStrategy::FailAll, int $concurrency = 1): self
    {
        if ($count < 1) {
            throw new ConfigurationException(new Message('pagination.page_count_must_be_greater_than_0'));
        }

        return new self(mode: PaginationMode::Pages, pages: $count, failStrategy: $failStrategy, concurrency: $concurrency);
    }

    public static function range(int $from, int $to, FailStrategy $failStrategy = FailStrategy::FailAll, int $concurrency = 1): self
    {
        if ($from < 1 || $to < $from) {
            throw new ConfigurationException(new Message('pagination.invalid_page_range'));
        }

        return new self(mode: PaginationMode::Range, from: $from, to: $to, failStrategy: $failStrategy, concurrency: $concurrency);
    }

    public function isSingle(): bool
    {
        return $this->mode === PaginationMode::Single;
    }
}
