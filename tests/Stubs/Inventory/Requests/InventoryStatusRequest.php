<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Inventory\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/inventory/status')]
final class InventoryStatusRequest extends AbstractRequest
{
}
