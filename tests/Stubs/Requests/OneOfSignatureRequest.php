<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\RequestDiscriminator;
use ApiSutra\Attributes\Request\RequestOneOf;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Request\OneOfMode;

#[Post('/crypto/v3/signatures')]
#[RequestOneOf(
    name: 'signature_payload',
    variants: [
        'cloudcrypt' => ['certificateId'],
        'goskey' => ['goskeyData'],
    ],
    requiredCommon: ['type', 'contents'],
    mode: OneOfMode::ExactlyOne,
)]
#[RequestDiscriminator(
    field: 'type',
    map: [
        'KONTUR_UC' => 'cloudcrypt',
        'GOSKEY' => 'goskey',
    ],
)]
final class OneOfSignatureRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public string $type,
        #[Body]
        public ?string $contents = null,
        #[Body]
        public ?string $certificateId = null,
        #[Body]
        public ?array $goskeyData = null,
    ) {}
}
