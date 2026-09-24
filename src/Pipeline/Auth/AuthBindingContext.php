<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Auth;

use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use Psr\SimpleCache\CacheInterface;

final readonly class AuthBindingContext
{
    public function __construct(
        public ClockInterface $clock,
        public CacheInterface $cache,
        public string $identity,
    ) {
    }
}
