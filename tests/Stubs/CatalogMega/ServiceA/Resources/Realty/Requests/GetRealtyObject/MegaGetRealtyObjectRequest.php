<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\CatalogMega\ServiceA\Resources\Realty\Requests\GetRealtyObject;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\OperationDescriptor;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\CatalogMega\ServiceA\Resources\Realty\Requests\GetRealtyObject\Dto\MegaRealtyObjectDto;

#[Get('/mega/serviceA/realty/object')]
#[Returns(MegaRealtyObjectDto::class)]
#[OperationDescriptor(title: 'ServiceA: Realty object')]
final class MegaGetRealtyObjectRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $id,
    ) {}
}
