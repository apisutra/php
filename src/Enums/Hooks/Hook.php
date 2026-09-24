<?php

declare(strict_types=1);

namespace ApiSutra\Enums\Hooks;

enum Hook: string
{
    case BeforeSend = 'before_send';
    case AfterResponse = 'after_response';
    case BeforeHydrate = 'before_hydrate';
    case AfterHydrate = 'after_hydrate';
}
