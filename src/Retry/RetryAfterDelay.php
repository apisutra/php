<?php

declare(strict_types=1);

namespace ApiSutra\Retry;

use ApiSutra\VO\Http\ProviderResponse;
use Closure;
use DateTimeImmutable;
use DateTimeZone;

/** Разбор серверного ожидания без неявного преобразования произвольного текста в дату. */
final readonly class RetryAfterDelay
{
    private Closure $now;

    public function __construct(?callable $now = null)
    {
        $this->now = $now === null ? static fn (): int => time() : Closure::fromCallable($now);
    }

    public function forResponse(ProviderResponse $response): int
    {
        if (!in_array($response->status, [429, 503], true)) {
            return 0;
        }
        return $this->fromSeconds($this->seconds($response->header('Retry-After')));
    }

    /** Неизвестное значение отличается от корректного нуля. */
    public function seconds(?string $header, ?int $now = null): ?int
    {
        $value = trim($header ?? '');
        if (preg_match('/^[0-9]+$/D', $value)) {
            $digits = ltrim($value, '0');
            $maximum = (string) intdiv(PHP_INT_MAX, 1_000_000);
            if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
                return null;
            }
            return (int) $digits;
        }
        foreach (['D, d M Y H:i:s \G\M\T', 'l, d-M-y H:i:s \G\M\T', 'D M j H:i:s Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value, new DateTimeZone('GMT'));
            if ($date !== false && DateTimeImmutable::getLastErrors() === false) {
                $seconds = max(0, $date->getTimestamp() - ($now ?? ($this->now)()));
                return $seconds <= intdiv(PHP_INT_MAX, 1_000_000) ? $seconds : null;
            }
        }
        return null;
    }

    public function fromSeconds(?int $seconds): int
    {
        return $seconds !== null && $seconds > 0 && $seconds <= intdiv(PHP_INT_MAX, 1_000_000)
            ? $seconds * 1000
            : 0;
    }
}
