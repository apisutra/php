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

final readonly class TransportOptions
{
    public function __construct(
        public int $timeoutMs = 0,
        public int $connectTimeoutMs = 0,
        public ?ExecutionBudget $budget = null,
        public ?RequestDestination $destination = null,
        public ?FileTransferOptions $fileTransfer = null,
        public ?StreamInterface $sink = null,
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
        return new self($timeout, $connect, $this->budget, $this->destination, $this->fileTransfer, $this->sink);
    }

    public function hasLimits(): bool
    {
        return $this->timeoutMs > 0 || $this->connectTimeoutMs > 0 || $this->budget?->deadlineMs !== null;
    }
}
