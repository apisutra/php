<?php

declare(strict_types=1);

namespace ApiSutra\Response;

/**
 * Клиентский ответ для приложений.
 */
readonly class ClientResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status,
        public array $headers = [],
        public mixed $body = null,
    ) {
    }
}
