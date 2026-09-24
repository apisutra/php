<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Transport;

use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Exceptions\Request\CooldownException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Localization\Message;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LogLevel;
use ApiSutra\Execution\Admission\AdmissionScope;

/** @internal Согласует собственный retry и общий запрет; не отправляет HTTP и не списывает квоты. */
final readonly class CooldownCoordinator
{
    public function __construct(private CooldownStore $store, private SleeperInterface $sleeper, private AuditLogger $logger)
    {
    }

    public function extend(?ResolvedCooldown $rule, int $delayMs, int $receivedMs, PipelineContext $context): void
    {
        if ($rule === null) {
            return;
        }
        $update = $this->store->extend($rule->key, $delayMs, $receivedMs, $context);
        $this->store->flush($context);
        if ($update?->extended) {
            $this->logger->log(LogLevel::DEBUG, new Message('rate_limit.cooldown_extended'), [
                ...$context->trace->logContext(), 'retryAfterMs' => $update->remainingMs,
            ]);
        }
    }

    public function flush(PipelineContext $context): void
    {
        $this->store->flush($context);
    }

    public function preflight(?ResolvedCooldown $rule, PipelineContext $context): void
    {
        if ($rule?->config->behavior !== RateLimitBehavior::Throw) {
            return;
        }
        $context->budget->check('cooldown_wait');
        $remaining = $this->store->remainingMs($rule->key, $context);
        $this->store->flush($context);
        if ($remaining > 0) {
            AdmissionScope::refuse('server_cooldown_active', $remaining, new CooldownException($remaining, $context->lastResponse));
            throw new CooldownException($remaining, $context->lastResponse);
        }
    }

    public function wait(?ResolvedCooldown $rule, PipelineContext $context, int $retryNotBeforeMs, int &$additionalWaitMs, bool $final = false): void
    {
        $budget = $context->budget;
        while (true) {
            $cooldown = $rule === null ? 0 : $this->store->remainingMs($rule->key, $context);
            $observed = $budget->clock->monotonicMs();
            $retry = max(0, $retryNotBeforeMs - $observed);
            $stage = $cooldown > $retry ? 'cooldown_wait' : 'retry_wait';
            $budget->check($stage);
            if ($cooldown > 0) {
                AdmissionScope::refuse('server_cooldown_active', $cooldown, new CooldownException($cooldown, $context->lastResponse));
            }
            if ($cooldown > 0 && $rule->config->behavior === RateLimitBehavior::Throw) {
                throw new CooldownException($cooldown, $context->lastResponse);
            }
            $delay = max($retry, $cooldown);
            if ($delay === 0) {
                if (!$final) {
                    $this->store->flush($context);
                }
                return;
            }
            $remaining = $budget->remainingMs();
            if ($remaining !== null && $delay >= $remaining) {
                throw new ExecutionDeadlineException($stage);
            }
            $limit = $rule?->config->maxAdditionalWaitMs ?? ($remaining === null ? 1000 : null);
            if ($limit !== null && max(0, $cooldown - $retry) > max(0, $limit - $additionalWaitMs)) {
                throw new CooldownException($cooldown, $context->lastResponse);
            }
            $this->store->flush($context);
            $this->logger->log(LogLevel::INFO, new Message('rate_limit.cooldown_wait'), [
                ...$context->trace->logContext(), 'stage' => $stage, 'delayMs' => $delay,
            ]);
            $started = $budget->clock->monotonicMs();
            $delay = max(0, $delay - max(0, $started - $observed));
            $retry = max(0, $retryNotBeforeMs - $started);
            try {
                $budget->wait($delay, $this->sleeper, $stage);
            } finally {
                $extra = max(0, $budget->clock->monotonicMs() - $started - $retry);
                $additionalWaitMs += min(PHP_INT_MAX - $additionalWaitMs, $extra);
            }
        }
    }
}
