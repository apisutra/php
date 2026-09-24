<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Localization;

use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Localization\Message;
use Throwable;

final class ProviderException extends SdkException
{
    public function __construct(public readonly int $limit, ?Throwable $previous = null)
    {
        parent::__construct(new Message('fixture.limit', ['limit' => $limit]), 19, $previous);
    }

    protected function copyForLocalization(): static
    {
        return new self($this->limit, $this);
    }
}
