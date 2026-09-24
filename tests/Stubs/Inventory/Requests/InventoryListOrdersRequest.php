<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Inventory\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/inventory/orders')]
final class InventoryListOrdersRequest extends AbstractRequest
{
}
