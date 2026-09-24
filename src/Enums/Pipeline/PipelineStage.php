<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Pipeline;

enum PipelineStage: string
{
    case Started = 'started';
    case BeforeSend = 'before_send';
    case HttpRequest = 'http_request';
    case HttpResponse = 'http_response';
    case BeforeHydrate = 'before_hydrate';
    case AfterHydrate = 'after_hydrate';
    case Completed = 'completed';
    case Failed = 'failed';
    case Abandoned = 'abandoned';
}
