<?php

declare(strict_types=1);

namespace ApiSutra\Result;

use ApiSutra\VO\Errors\ClientError;
use ApiSutra\VO\Errors\ErrorContextFactoryInterface;

/**
 * Кеш типизированного контекста ошибок.
 */
final class ErrorContextCache
{
    /**
     * @var array<int, object|null>|null
     */
    private ?array $contexts = null;

    /**
     * @param array<int, ClientError> $errors
     * @return array<int, object|null>
     */
    public function get(array $errors, ErrorContextFactoryInterface $factory): array
    {
        if ($this->contexts === null) {
            $this->contexts = array_map(
                static fn (ClientError $error): ?object => $factory->make($error),
                $errors,
            );
        }

        return $this->contexts;
    }
}
