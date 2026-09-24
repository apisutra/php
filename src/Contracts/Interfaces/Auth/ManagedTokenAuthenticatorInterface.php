<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Auth;

use ApiSutra\Enums\Http\TransmissionState;
use ApiSutra\Pipeline\Auth\AuthBindingContext;
use ApiSutra\VO\Http\ProviderResponse;

/** Необязательный lifecycle stateful auth; обычный AuthenticatorInterface не меняется. */
interface ManagedTokenAuthenticatorInterface extends AuthenticatorInterface
{
    public function bind(AuthBindingContext $context): self;
    public function bindingIdentity(): string;
    public function reloadToken(): void;
    public function tokenVersion(): ?string;
    public function canRefresh(): bool;
    public function refreshAttempts(int $configured): int;
    public function refreshLockProvider(): ?AuthLockProviderInterface;
    public function refreshFailed(TransmissionState $transmission, ?ProviderResponse $response): void;
}
