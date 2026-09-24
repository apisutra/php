<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Auth;

use ApiSutra\Contracts\Interfaces\Auth\AuthorizationParamsProviderInterface;
use ApiSutra\VO\Http\PreparedRequest;

final readonly class SignatureParamsProvider implements AuthorizationParamsProviderInterface
{
    public function __construct(
        private string $secret,
        private int $timestamp,
    ) {}

    public function resolve(PreparedRequest $request): array
    {
        return [
            'ts' => $this->timestamp,
            'sign' => hash_hmac('sha256', $request->url . $this->timestamp, $this->secret),
        ];
    }
}
