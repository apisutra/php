<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Continuation;

use ApiSutra\Contracts\Interfaces\Continuation\ContinuationModeApplicatorInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;
use ApiSutra\Serialization\VO\RequestPartsBag;
use ApiSutra\VO\Pipeline\PipelineContext;

final class TestContinuationModeApplicator implements ContinuationModeApplicatorInterface
{
    #[\Override]
    public function apply(
        RequestInterface $request,
        RequestPartsBag $parts,
        ContinuationMode $mode,
        ?PipelineContext $context = null,
    ): RequestPartsBag {
        if (($parts->query['manual_async']['value'] ?? null) === true && $mode === ContinuationMode::Sync) {
            throw new ContinuationConfigurationException('Конфликт mode и ручного provider-флага async');
        }

        $parts->query['provider_async'] = [
            'value' => $mode === ContinuationMode::Async,
            'format' => null,
        ];

        return $parts;
    }
}
