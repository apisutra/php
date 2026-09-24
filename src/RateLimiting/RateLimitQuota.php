<?php

declare(strict_types=1);

namespace ApiSutra\RateLimiting;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

final readonly class RateLimitQuota
{
    public function __construct(
        public string $key,
        public int $limit,
        public int $periodMs,
    ) {
        if ($key === '' || $limit < 1 || $periodMs < 1 || $periodMs > intdiv(PHP_INT_MAX, 1000)) {
            throw new ConfigurationException(
                new Message('ratelimiting.invalid_quota_a_key_positive_limit_and_representable_periodms'),
            );
        }
    }

    /**
     * @param list<self> $quotas
     * @return list<self>
     */
    public static function normalize(array $quotas): array
    {
        $unique = [];
        foreach ($quotas as $quota) {
            $previous = $unique[$quota->key] ?? null;
            if (
                $previous !== null
                && ($previous->limit !== $quota->limit || $previous->periodMs !== $quota->periodMs)
            ) {
                throw new ConfigurationException(new Message('ratelimiting.conflicting_definitions_of_the_same_quota'));
            }
            $unique[$quota->key] = $quota;
        }
        return array_values($unique);
    }
}
