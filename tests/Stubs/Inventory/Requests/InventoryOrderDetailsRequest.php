<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Inventory\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Core\AbstractRequest;

#[Get('/inventory/orders/{orderId}')]
final class InventoryOrderDetailsRequest extends AbstractRequest
{
    public function __construct(
        #[Path('orderId')]
        public string $orderId,
    ) {}
}
