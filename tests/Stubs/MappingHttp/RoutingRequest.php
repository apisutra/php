<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingHttp;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Attributes\Request\File;
use ApiSutra\Attributes\Request\Header;
use ApiSutra\Attributes\Request\Ignore;
use ApiSutra\Attributes\Request\Path;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Http\HttpMethod;

class RoutingRequest implements RequestInterface
{
    #[Ignore, Cast(CountingCast::class, new Argument('ignored'))]
    public int $ignored = 1;
    #[Header('X-Token'), Query('header_query'), Cast(CountingCast::class, new Argument('header'))]
    public int $header = 3;
    #[Path('id'), Query('path_query'), Cast(CountingCast::class, new Argument('path'))]
    public int $path = 7;
    #[Query('q'), Cast(CountingCast::class, new Argument('query'))]
    public int $query = 4;
    #[Query('payload_name'), Body(nested: 'data.value'), Cast(CountingCast::class, new Argument('body'))]
    public int $body = 5;
    #[Cast(CountingCast::class, new Argument('free'))]
    public int $free = 6;
    #[Query(nullable: true)]
    public ?int $nullable = null;
    #[File('upload'), Header('X-File'), Cast(CountingCast::class, new Argument('file'))]
    public mixed $file = null;
    #[Cast(CountingCast::class, new Argument('static'))]
    public static int $counter = 0;

    public function __construct(private HttpMethod $method = HttpMethod::GET, private string $endpoint = '/items/{id}')
    {
    }

    public function getMethod(): HttpMethod
    {
        return $this->method;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getResponseType(): ?string
    {
        return null;
    }
}
