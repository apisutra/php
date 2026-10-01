<?php

declare(strict_types=1);

namespace ApiSutra\VO\Http;

use ApiSutra\Localization\Message;
use ApiSutra\Http\RequestDestination;
use ApiSutra\VO\Files\FileTransferOptions;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Timing\ExecutionBudget;
use Psr\Http\Message\StreamInterface;
use Closure;

final readonly class TransportOptions
{
    /**
     * @param Closure(float, float, float, float): void|null $transferProgress
     * Сырые счётчики: download total, downloaded, upload total, uploaded (байты).
     * Нулевой total означает неизвестный размер; callback вызывается внутри передачи.
     */
    public function __construct(
        public int $timeoutMs = 0,
        public int $connectTimeoutMs = 0,
        public ?ExecutionBudget $budget = null,
        public ?RequestDestination $destination = null,
        public ?FileTransferOptions $fileTransfer = null,
        public ?StreamInterface $sink = null,
        public ?Closure $transferProgress = null,
    ) {
        if ($timeoutMs < 0 || $connectTimeoutMs < 0) {
            throw new ConfigurationException(new Message('vo.transport_timeouts_must_be_0'));
        }
    }

    /** Пересчитывается непосредственно перед HTTP, включая ожидания транспортного декоратора. */
    public function effective(): self
    {
        $remaining = $this->budget?->remainingMs();
        if ($remaining === 0) {
            throw new ExecutionDeadlineException('http');
        }
        $timeout = $remaining === null ? $this->timeoutMs
            : ($this->timeoutMs === 0 ? $remaining : min($this->timeoutMs, $remaining));
        $connect = $this->connectTimeoutMs;
        if ($timeout > 0 && $connect > 0) {
            $connect = min($connect, $timeout);
        }
        return new self($timeout, $connect, $this->budget, $this->destination, $this->fileTransfer, $this->sink, $this->transferProgress);
    }

    /** @param Closure(float, float, float, float): void $callback */
    public function withTransferProgress(Closure $callback): self
    {
        return new self(
            $this->timeoutMs,
            $this->connectTimeoutMs,
            $this->budget,
            $this->destination,
            $this->fileTransfer,
            $this->sink,
            $callback,
        );
    }

    public function hasLimits(): bool
    {
        return $this->timeoutMs > 0 || $this->connectTimeoutMs > 0 || $this->budget?->deadlineMs !== null;
    }
}
