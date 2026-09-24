<?php

declare(strict_types=1);

namespace ApiSutra\Pagination;

use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Contracts\Interfaces\Pagination\PaginableInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Request\PaginationOptions;
use ApiSutra\Request\RequestExecution;
use ApiSutra\VO\Metadata\PaginationMeta;

abstract class AbstractPaginatedRequest extends AbstractRequest implements PaginableInterface
{
    public function paginate(): Paginator
    {
        return new Paginator($this, $this->getOptions(), PaginationOptions::empty());
    }

    public function withPage(int $page): RequestExecutionInterface
    {
        return new RequestExecution(
            request: $this,
            options: $this->getOptions(),
            paginationOptions: PaginationOptions::empty()->withPage($page),
        );
    }

    public function withLimit(int $limit): RequestExecutionInterface
    {
        return new RequestExecution(
            request: $this,
            options: $this->getOptions(),
            paginationOptions: PaginationOptions::empty()->withLimit($limit),
        );
    }

    public function withCursor(?string $cursor): RequestExecutionInterface
    {
        return new RequestExecution(
            request: $this,
            options: $this->getOptions(),
            paginationOptions: PaginationOptions::empty()->withCursor($cursor),
        );
    }

    public function extractMeta(array $response): PaginationMeta
    {
        return $this->paginationHelper()->extractMeta($this, $response);
    }
}
