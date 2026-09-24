<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\RequestDiscriminator;
use ApiSutra\Attributes\Request\RequestOneOf;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Request\OneOfMode;

#[Post('/crypto/v3/signatures/nested')]
#[RequestOneOf(
    name: 'signature_payload_nested',
    variants: [
        'cloudcrypt' => ['signature.certificateId'],
        'goskey' => ['signature.goskey.data'],
    ],
    requiredCommon: ['signature.contents', 'type'],
    mode: OneOfMode::ExactlyOne,
)]
#[RequestDiscriminator(
    field: 'type',
    map: [
        'KONTUR_UC' => 'cloudcrypt',
        'GOSKEY' => 'goskey',
    ],
)]
final class OneOfNestedSignatureRequest extends AbstractRequest
{
    /**
     * @param array<string, mixed> $signature
     */
    public function __construct(
        #[Body]
        public string $type,
        #[Body]
        public array $signature,
    ) {}
}
