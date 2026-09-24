<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Request\RequestOptions;

#[Get('/provider-options')]
final class RequestOptionsProviderFallbackRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $query = 'q',
    ) {}

    #[\Override]
    public function getOptions(): RequestOptions
    {
        return RequestOptions::empty()
            ->withBaseUrl('https://override.test')
            ->withTraceId('trace-from-provider-options');
    }
}
