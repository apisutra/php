<?php

declare(strict_types=1);

namespace ApiSutra\Result;

use ApiSutra\Collections\ResultCollection;
use ApiSutra\VO\Metadata\BatchMeta;

readonly class BatchResult extends ExecutionResult
{
    /**
     * Возвращает результаты как коллекцию.
     */
    public function results(): ResultCollection
    {
        return $this->nestedResults();
    }

    /**
     * Возвращает успешные результаты как коллекцию.
     */
    public function successful(): ResultCollection
    {
        return $this->results()->successful();
    }

    /**
     * Возвращает неуспешные результаты как коллекцию.
     */
    public function failed(): ResultCollection
    {
        return $this->results()->failed();
    }

    public function get(int $index): ?ExecutionResult
    {
        return $this->nested[$index] ?? null;
    }

    /**
     * Возвращает результаты по классу как коллекцию.
     */
    public function getByClass(string $class): ResultCollection
    {
        return $this->results()->getByClass($class);
    }

    public function meta(): BatchMeta
    {
        return $this->meta instanceof BatchMeta
            ? $this->meta
            : new BatchMeta(total: 0, successful: 0, failed: 0, partial: 0);
    }
}
