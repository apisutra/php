<?php

declare(strict_types=1);

namespace ApiSutra\Continuation;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Continuation\ContinuationStateResolverInterface;
use ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Support\ArrayPath;

final readonly class FinalPathStateResolver implements ContinuationStateResolverInterface
{
    public function resolve(ExecutionResult $result, ContinuationContext $context): ContinuationState
    {
        $path = $context->unwrap;
        if ($path === null || trim($path) === '') {
            throw new ContinuationConfigurationException(new Message('continuation.finalpathstateresolver_requires_a_non_empty_unwrap'));
        }

        $response = $result->response;
        if ($response?->body === null || !str_starts_with(ltrim($response->body), '{')) {
            return ContinuationState::pending();
        }
        $data = $response->json();
        if (!is_array($data)) {
            return ContinuationState::pending();
        }
        $resolved = ArrayPath::getByPathWithStatus($data, $path);

        return $resolved->isMissing() || $resolved->value === null
            ? ContinuationState::pending()
            : ContinuationState::ready($resolved->value, $path);
    }
}
