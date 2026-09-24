<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Testing;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\VO\Http\ProviderResponse;
use Throwable;

final class RecordingException extends SdkException
{
    public function __construct(public readonly ProviderResponse $response, Throwable $previous)
    {
        parent::__construct(new Message('errors.failed_to_record_the_completed_http_request_fixture'), previous: $previous);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this->response, $this);
    }
}
