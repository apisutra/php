<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Batch;

use ApiSutra\Localization\Message;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

/**
 * Последовательная стратегия выполнения batch.
 */
final class SequentialBatchStrategy implements BatchStrategyInterface
{
    /**
     * @inheritDoc
     */
    public function execute(BatchContext $context, array $requests): array
    {
        $client = $context->client;
        if ($client === null) {
            throw new ConfigurationException(new Message('execution.no_client_specified_for_batch_execution'));
        }

        $results = [];
        foreach ($requests as $index => $request) {
            $result = $context->execute($request);
            $results[$index] = $result;

            if ($context->failStrategy === FailStrategy::FailAll && $result->isFailed()) {
                break;
            }
        }

        ksort($results);
        return array_values($results);
    }
}
