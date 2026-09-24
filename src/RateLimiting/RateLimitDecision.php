<?php

declare(strict_types=1);

namespace ApiSutra\RateLimiting;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Core\InvalidArgumentException;

final readonly class RateLimitDecision
{
    /** @var list<string> */
    public array $blockedIds;

    /** @param array<array-key, mixed> $blockedIds Непроверенный ответ внешнего backend. */
    public function __construct(
        public bool $granted,
        array $blockedIds = [],
        public int $retryAfterMs = 0,
    ) {
        if ($granted ? ($blockedIds !== [] || $retryAfterMs !== 0) : ($blockedIds === [] || $retryAfterMs < 1)) {
            throw new InvalidArgumentException(new Message('ratelimiting.invalid_quota_accounting_result'));
        }
        $ids = [];
        foreach ($blockedIds as $id) {
            if (!is_string($id) || $id === '') {
                throw new InvalidArgumentException(new Message('ratelimiting.invalid_blocking_quota_identifier'));
            }
            $ids[] = $id;
        }
        if (!array_is_list($blockedIds) || count(array_unique($ids)) !== count($ids)) {
            throw new InvalidArgumentException(new Message('ratelimiting.invalid_blocking_quota_list'));
        }
        $this->blockedIds = $ids;
    }
}
