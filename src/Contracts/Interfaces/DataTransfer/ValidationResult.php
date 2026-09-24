<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\DataTransfer;

use ApiSutra\VO\Errors\ValidationError;

interface ValidationResult
{
    public function passed(): bool;
    public function failed(): bool;

    /**
     * @return array<ValidationError>
     */
    public function errors(): array;
}
