<?php

declare(strict_types=1);

namespace ApiSutra\VO\Files;

final readonly class FileTransferOptions
{
    public function __construct(
        public bool $upload = false,
        public bool $download = false,
        public ?DownloadTarget $target = null,
    ) {
    }
}
