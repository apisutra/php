<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Preparation;

use ApiSutra\Localization\Message;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\VO\Http\TransportOptions;
use ApiSutra\VO\Pipeline\PipelineContext;

final class TimeoutResolver
{
    public static function resolve(PipelineContext $context): TransportOptions
    {
        $request = $context->request;
        $attribute = $request instanceof AbstractRequest ? $request->getTimeoutAttribute() : null;
        $options = $context->options ?? ($request instanceof AbstractRequest ? $request->getOptions() : null);
        return new TransportOptions(
            self::milliseconds($options?->getTimeoutOverride() ?? $attribute->seconds ?? $context->config->timeout),
            self::milliseconds($options?->getConnectTimeoutOverride() ?? $attribute->connectTimeout ?? $context->config->connectTimeout),
            $context->budget,
            $context->destination,
            $context->fileTransfer,
        );
    }

    private static function milliseconds(int $seconds): int
    {
        if ($seconds < 0 || $seconds > intdiv(PHP_INT_MAX, 1000)) {
            throw new ConfigurationException(new Message('pipeline.invalid_timeout_connecttimeout_in_seconds'));
        }
        return $seconds * 1000;
    }
}
