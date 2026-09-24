<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\ContinuationFinalDto;
use ApiSutra\Tests\Stubs\Dto\ContinuationStartDto;

#[Get('/continuation/start')]
#[Returns(ContinuationStartDto::class)]
#[ContinuationResult(
    finalType: ContinuationFinalDto::class,
    pollRequest: ContinuationPollRequest::class,
    unwrap: 'data',
)]
final class ContinuationStartRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $id,
    ) {}
}
