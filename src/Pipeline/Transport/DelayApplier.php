<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Transport;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\Timing\SystemClock;
use ApiSutra\Timing\CooperativeSleeper;

final readonly class DelayApplier
{
    public function __construct(
        private ClientConfig $config,
        private SleeperInterface $sleeper = new CooperativeSleeper(),
    ) {
    }

    public function apply(RequestInterface $request, ?RequestOptions $options = null, ?ExecutionBudget $budget = null): void
    {
        $delay = $this->config->delay;
        if ($options?->getDelayOverride() !== null) {
            $delay = $options->getDelayOverride();
        } elseif ($request instanceof AbstractRequest && $request->getDelayOverride() !== null) {
            $delay = $request->getDelayOverride();
        }

        if ($delay > 0) {
            ($budget ?? new ExecutionBudget(new SystemClock()))->wait($delay, $this->sleeper, 'request_delay');
        }
    }
}
