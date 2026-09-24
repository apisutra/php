<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Auth;

use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use Psr\SimpleCache\CacheInterface;

final readonly class AuthBinding
{
    public function __construct(
        public AuthenticatorInterface $auth,
        public CacheInterface $cache,
        public string $lockKey,
    ) {
    }
}
