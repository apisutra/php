<?php

declare(strict_types=1);

namespace ApiSutra\Pagination\Traversal;

use ApiSutra\Config\PaginationConfig;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Enums\Pagination\PaginationMode;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Request\PaginationOptions;
use ApiSutra\VO\Metadata\PaginationMeta;

/** @internal Состояние одного обхода; не выполняет запросы и не накапливает результаты. */
final class PaginationProgress
{
    public private(set) ?int $limit;
    public private(set) ?string $cursor;
    public private(set) ?Message $failure = null;
    public private(set) ?string $failureReason = null;
    public private(set) ?int $failurePage = null;
    private ?int $nextPage;
    private ?int $end;
    private int $issued = 0;
    private bool $stopped = false;
    private bool $maxLimited = false;
    private bool $bootstrapped = false;
    private ?int $lastMetaPage = null;
    /** @var array<string, true> */
    private array $visited = [];

    public function __construct(
        private readonly PaginationRule $rule,
        private readonly PaginationConfig $config,
        PaginationOptions $options,
    ) {
        $start = $rule->mode === PaginationMode::Range ? $rule->from : ($options->getPage() ?? 1);
        if ($start === null || $start < 1) {
            throw new ConfigurationException(new Message('pagination.invalid_page_range'));
        }
        $this->limit = $options->getLimit();
        if ($this->limit !== null && $this->limit < 1) {
            throw new ConfigurationException(new Message('pagination.page_size_must_be_positive'));
        }
        if ($config->offsetBased && $this->limit === null) {
            throw new ConfigurationException(new Message('pagination.offset_based_pagination_requires_limit'));
        }
        $this->cursor = $options->getCursor();
        if ($rule->concurrency > 1 && $this->isCursorBased()) {
            throw new ConfigurationException(new Message('pagination.concurrent_cursor_not_supported'));
        }
        $this->nextPage = $start;
        $this->end = match ($rule->mode) {
            PaginationMode::Pages => $this->lastPage($start, $rule->pages ?? 1),
            PaginationMode::Range => $rule->to,
            default => null,
        };
        $this->offset($start);
        if ($this->end !== null) {
            $this->offset($this->end);
        }
        if ($this->cursor !== null) {
            $this->visited[hash('sha256', $this->cursor)] = true;
        }
    }

    public function next(): ?int
    {
        if ($this->stopped || !$this->hasRemainder()) {
            return null;
        }
        if ($this->config->maxPages !== null && $this->config->maxPages > 0 && $this->issued >= $this->config->maxPages) {
            // Ответы уже выданных страниц ещё могут подтвердить естественный конец.
            $this->maxLimited = true;
            return null;
        }
        $page = $this->nextPage;
        $this->nextPage = $page === PHP_INT_MAX ? null : $page + 1;
        $this->issued++;
        return $page;
    }

    public function stop(): void
    {
        $this->stopped = true;
    }

    public function accept(int $page, bool $failed, ?PaginationMeta $meta): void
    {
        if ($failed) {
            if ($this->rule->failStrategy === FailStrategy::FailAll || $this->isCursorBased()) {
                $this->stop();
            }
            if (!$this->bootstrapped && $this->rule->concurrency > 1 && $this->rule->mode === PaginationMode::All) {
                $this->stop();
            }
            return;
        }
        if ($meta === null) {
            return;
        }
        if ($this->rule->concurrency > 1) {
            $this->acceptConcurrent($page, $meta);
        } else {
            $this->acceptSequential($page, $meta);
        }
    }

    public function finish(): void
    {
        if ($this->maxLimited && !$this->stopped && $this->hasRemainder()) {
            $this->fail(new Message('pagination.page_limit_reached'), $this->nextPage ?? PHP_INT_MAX, 'pagination_max_pages_reached');
        }
    }

    public function offset(int $page): int
    {
        if (!$this->config->offsetBased) {
            return $page;
        }
        $limit = $this->limit ?? 1;
        if ($page - 1 > intdiv(PHP_INT_MAX, $limit)) {
            throw new ConfigurationException(new Message('pagination.invalid_page_range'));
        }
        return ($page - 1) * $limit;
    }

    private function hasRemainder(): bool
    {
        return $this->nextPage !== null && ($this->end === null || $this->nextPage <= $this->end);
    }

    private function lastPage(int $start, int $count): int
    {
        if ($count < 1 || $count - 1 > PHP_INT_MAX - $start) {
            throw new ConfigurationException(new Message('pagination.invalid_page_range'));
        }
        return $start + ($count - 1);
    }

    private function isCursorBased(?string $nextCursor = null): bool
    {
        return $this->config->cursorParam !== null || $this->cursor !== null || $nextCursor !== null;
    }

    private function acceptSequential(int $page, PaginationMeta $meta): void
    {
        if ($meta->hasMore && $this->nextPage === null && $this->end === null) {
            $this->fail(new Message('pagination.invalid_page_range'), $page);
            return;
        }
        if (!$meta->hasMore || !$this->hasRemainder() || ($meta->totalPages() !== null && $page >= $meta->totalPages())) {
            $this->stop();
            return;
        }
        if ($this->isCursorBased($meta->nextCursor)) {
            if ($meta->nextCursor === null || $meta->nextCursor === $this->cursor || isset($this->visited[hash('sha256', $meta->nextCursor)])) {
                $this->fail(new Message('pagination.cursor_did_not_change'), $page);
                return;
            }
        } elseif ($meta->perPage > 0 && $this->lastMetaPage !== null && $meta->currentPage <= $this->lastMetaPage) {
            $this->fail(new Message('pagination.page_did_not_change'), $page);
            return;
        }
        $this->lastMetaPage = $meta->currentPage;
        $this->cursor = $meta->nextCursor;
        if ($this->cursor !== null) {
            $this->visited[hash('sha256', $this->cursor)] = true;
        }
    }

    private function acceptConcurrent(int $page, PaginationMeta $meta): void
    {
        if ($meta->nextCursor !== null) {
            if (!$this->bootstrapped) {
                throw new ConfigurationException(new Message('pagination.concurrent_cursor_not_supported'));
            }
            $this->fail(new Message('pagination.page_metadata_changed'), $page, 'pagination_metadata_changed');
            return;
        }
        if (
            ($meta->total !== null && $meta->total < 0) || $meta->perPage < 0
            || ($meta->perPage > 0 && ($meta->currentPage !== $page || ($this->limit !== null && $this->limit !== $meta->perPage)))
        ) {
            $this->fail(new Message('pagination.page_metadata_changed'), $page, 'pagination_metadata_changed');
            return;
        }
        if (!$this->bootstrapped) {
            $this->limit ??= $meta->perPage > 0 ? $meta->perPage : null;
            if ($meta->hasMore && $this->rule->mode === PaginationMode::All && ($meta->total === null || $meta->perPage <= 0)) {
                throw new ConfigurationException(new Message('pagination.concurrent_all_requires_total'));
            }
            $this->bootstrapped = true;
        }
        if ($meta->total !== null && $meta->perPage > 0) {
            $pages = intdiv($meta->total, $meta->perPage) + (int) ($meta->total % $meta->perPage !== 0);
            $this->end = $this->end === null ? $pages : min($this->end, $pages);
        }
        if (!$meta->hasMore) {
            $this->end = $this->end === null ? $page : min($this->end, $page);
        }
        if ($page === PHP_INT_MAX && $meta->hasMore && $this->end === null) {
            $this->fail(new Message('pagination.invalid_page_range'), $page);
        }
    }

    private function fail(Message $message, int $page, string $reason = 'pagination_stalled'): void
    {
        $this->stop();
        if ($this->failurePage === null || $page < $this->failurePage) {
            $this->failure = $message;
            $this->failureReason = $reason;
            $this->failurePage = $page;
        }
    }
}
