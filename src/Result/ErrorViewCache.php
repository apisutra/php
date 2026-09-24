<?php

declare(strict_types=1);

namespace ApiSutra\Result;

use ApiSutra\Collections\ErrorCollection;
use ApiSutra\VO\Errors\ClientError;
use ApiSutra\VO\Errors\ClientErrorFactory;

/**
 * Простой кеш для маппинга ошибок.
 */
final class ErrorViewCache
{
    /**
     * @var array<int, ClientError>|null
     */
    private ?array $views = null;

    /**
     * @return array<int, ClientError>
     */
    public function get(ErrorCollection $errors, ClientErrorFactory $factory): array
    {
        if ($this->views === null) {
            $this->views = $factory->makeMany($errors);
        }

        return $this->views;
    }
}
