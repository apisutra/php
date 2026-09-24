<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Files;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Core\SdkException;
use Throwable;

final class FileTransferException extends SdkException
{
    public function __construct(
        public readonly string $stage,
        public readonly int $bytesWritten = 0,
        public readonly bool $partial = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct(new Message('errors.file_transfer_failed', ['stage' => $stage]), 0, $previous);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this->stage, $this->bytesWritten, $this->partial, $this);
    }
}
