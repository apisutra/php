<?php

declare(strict_types=1);

namespace ApiSutra\Execution\Admission;

use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Exceptions\ControlFlow\AdmissionRefused;
use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Execution\ExecutionLocal;
use ApiSutra\Timing\SystemClock;
use Closure;

/** Область отказа вместо ожидания; не содержит политики очереди и диагностического I/O. */
final class AdmissionScope
{
    /** @var ExecutionLocal<self>|null */
    private static ?ExecutionLocal $local = null;
    private ?int $notBeforeMs = null;
    private bool $open = false;

    public function __construct(private readonly ClockInterface $clock = new SystemClock())
    {
    }

    /** @template T
     * @param Closure(): T $operation
     * @return T
     */
    public function run(Closure $operation): mixed
    {
        $leave = (self::$local ??= new ExecutionLocal())->enter($this);
        $this->open = true;
        try {
            return $operation();
        } finally {
            $this->open = false;
            $leave();
        }
    }

    /** @internal Вызывается только после проверки реального допуска и бюджета. */
    public static function refuse(string $reason, int $retryAfterMs, RateLimitException $cause): void
    {
        $scope = self::$local?->get();
        if ($scope === null || !$scope->open) {
            return;
        }
        $now = $scope->clock->monotonicMs();
        $scope->notBeforeMs = max($scope->notBeforeMs ?? $now, $now + min(PHP_INT_MAX - $now, $retryAfterMs));
        throw new AdmissionRefused($scope, $reason, $cause);
    }

    /** @internal Останавливает подачу агрегата сразу, даже до доставки rejection. */
    public static function hasRefusal(): bool
    {
        $scope = self::$local?->get();
        return $scope !== null && $scope->open && $scope->refused();
    }

    public function refused(): bool
    {
        return $this->notBeforeMs !== null;
    }

    public function remainingMs(): int
    {
        return max(0, ($this->notBeforeMs ?? 0) - $this->clock->monotonicMs());
    }
}
