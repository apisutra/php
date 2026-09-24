<?php

declare(strict_types=1);

namespace ApiSutra\Collections;

use ArrayIterator;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Metadata\BatchMeta;
use ApiSutra\VO\Metadata\ResultSummary;
use IteratorAggregate;
use Override;
use Traversable;

final class ResultCollection implements IteratorAggregate
{
    /**
     * @param array<int, ExecutionResult> $items
     */
    public function __construct(
        private array $items,
    ) {
    }

    /**
     * @param array<int, ExecutionResult> $items
     */
    public static function make(array $items): self
    {
        return new self($items);
    }

    public function get(string $class): ?ExecutionResult
    {
        foreach ($this->items as $item) {
            if ($item->requestClass === $class) {
                return $item;
            }
        }

        return null;
    }

    public function firstFailure(): ?ExecutionResult
    {
        foreach ($this->items as $item) {
            if ($item->isFailed()) {
                return $item;
            }
        }
        return null;
    }

    public function hasErrors(): bool
    {
        // Ошибка считается по status результата, а не по наличию сообщений.
        foreach ($this->items as $item) {
            if ($item->isFailed()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Возвращает статус коллекции результатов.
     * PARTIAL — есть и успешные, и неуспешные.
     */
    public function resolveStatus(): ResultStatus
    {
        return $this->summarize()->status;
    }

    /**
     * Сформировать BatchMeta по результатам.
     * Метрика строится по статусам ExecutionResult.
     */
    public function toBatchMeta(): BatchMeta
    {
        $summary = $this->summarize();
        return new BatchMeta(
            total: $summary->total,
            successful: $summary->successful,
            failed: $summary->failed,
            partial: $summary->partial,
        );
    }

    /**
     * Сводные метрики за один проход.
     * SUCCESS только если все успешны, FAILED только если все неуспешны.
     */
    public function summarize(): ResultSummary
    {
        $successful = 0;
        $failed = 0;
        $partial = 0;

        foreach ($this->items as $item) {
            if ($item->isSuccess()) {
                $successful++;
                continue;
            }

            if ($item->isFailed()) {
                $failed++;
                continue;
            }

            if ($item->isPartial()) {
                $partial++;
            }
        }

        return ResultSummary::fromCounts($successful, $failed, $partial);
    }

    /**
     * Количество результатов.
     */
    public function countTotal(): int
    {
        return count($this->items);
    }

    /**
     * Количество успешных результатов.
     * Учитываются только результаты со статусом SUCCESS.
     */
    public function countSuccessful(): int
    {
        return count(array_filter(
            $this->items,
            static fn (ExecutionResult $item): bool => $item->isSuccess(),
        ));
    }

    /**
     * Количество неуспешных результатов.
     * Учитываются только результаты со статусом FAILED.
     */
    public function countFailed(): int
    {
        return count(array_filter(
            $this->items,
            static fn (ExecutionResult $item): bool => $item->isFailed(),
        ));
    }

    /**
     * Возвращает успешные результаты как коллекцию.
     */
    public function successful(): self
    {
        return self::make(array_values(array_filter(
            $this->items,
            static fn(ExecutionResult $item): bool => $item->isSuccess(),
        )));
    }

    /**
     * Возвращает неуспешные результаты как коллекцию.
     */
    public function failed(): self
    {
        return self::make(array_values(array_filter(
            $this->items,
            static fn(ExecutionResult $item): bool => $item->isFailed(),
        )));
    }

    /**
     * Возвращает результаты по классу как коллекцию.
     */
    public function getByClass(string $class): self
    {
        return self::make(array_values(array_filter(
            $this->items,
            static fn(ExecutionResult $item): bool => $item->requestClass === $class,
        )));
    }

    /**
     * @return array<int, ExecutionResult>
     */
    public function all(): array
    {
        return $this->items;
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
