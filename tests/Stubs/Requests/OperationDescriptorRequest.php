<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\OperationDescriptor;
use ApiSutra\Core\AbstractRequest;

#[Get('/operation-descriptor')]
#[OperationDescriptor(
    title: 'Operation title',
    description: 'Operation description',
    note: 'Operation note',
)]
final class OperationDescriptorRequest extends AbstractRequest
{
}
