<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Pagination\AbstractPaginatedRequest;

#[Get('/override-page')]
#[Pagination(pageParam: 'page', limitParam: 'limit')]
final class PaginatedOverrideRequest extends AbstractPaginatedRequest
{
    public static bool $withPageCalled = false;

    public static function reset(): void
    {
        self::$withPageCalled = false;
    }

    public function withPage(int $page): RequestExecutionInterface
    {
        self::$withPageCalled = true;

        return parent::withPage($page);
    }
}
