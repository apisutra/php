<?php

declare(strict_types=1);

namespace ApiSutra\Http;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

final class DestinationGuard
{
    public static function checkCapability(object $sender, ?RequestDestination $destination): void
    {
        if (!$destination?->requiresIsolation()) {
            return;
        }
        if (!$sender instanceof DestinationAwareInterface) {
            throw new ConfigurationException(new Message('http.does_not_support_request_destination_isolation', ['value0' => $sender::class]));
        }
        $sender->assertSupportsDestination($destination);
    }

    public static function checkContext(PipelineContext $context): void
    {
        if ($context->destination !== null && $context->preparedRequest !== null) {
            $context->destination->assertUrl($context->preparedRequest->url);
            $context->preparedRequest = $context->preparedRequest->with(destination: $context->destination);
        }
    }

    public static function checkRequest(PreparedRequest $request): void
    {
        $request->destination?->assertUrl($request->url);
    }
}
