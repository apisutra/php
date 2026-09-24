<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\OperationDescriptor;
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\Dto\MegaTaxInfoFinalDto;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\GetTaxInfo\Dto\MegaTaxInfoStartDto;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceB\Resources\Tax\Requests\PollTax\MegaPollTaxRequest;

#[Post('/mega/serviceB/tax/info')]
#[Returns(MegaTaxInfoStartDto::class)]
#[ContinuationResult(
    finalType: MegaTaxInfoFinalDto::class,
    pollRequest: MegaPollTaxRequest::class,
)]
#[OperationDescriptor(title: 'ServiceB: Tax info')]
final class MegaGetTaxInfoRequest extends AbstractRequest
{
}
