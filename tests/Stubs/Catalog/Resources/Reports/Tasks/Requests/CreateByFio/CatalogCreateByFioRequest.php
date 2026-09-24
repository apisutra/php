<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\CreateByFio;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\OperationDescriptor;
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\CreateByFio\Dto\CatalogCreateByFioFinalDto;
use ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\CreateByFio\Dto\CatalogCreateByFioStartDto;
use ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\PollTask\CatalogPollTaskRequest;

#[Post('/catalog/reports/tasks/by-fio')]
#[Returns(CatalogCreateByFioStartDto::class, unwrap: 'data')]
#[ContinuationResult(
    finalType: CatalogCreateByFioFinalDto::class,
    pollRequest: CatalogPollTaskRequest::class,
    unwrap: 'result',
)]
#[OperationDescriptor(
    title: 'Создать отчёт по ФИО',
    description: 'Async-операция: возвращает task envelope, финальный DTO достаётся через poll.',
)]
final class CatalogCreateByFioRequest extends AbstractRequest
{
}
