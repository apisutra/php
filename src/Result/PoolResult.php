<?php

declare(strict_types=1);

namespace ApiSutra\Result;

use ApiSutra\Collections\ResultCollection;

readonly class PoolResult extends ExecutionResult
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
}
