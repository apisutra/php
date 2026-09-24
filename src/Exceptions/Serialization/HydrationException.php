<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Serialization;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Serialization\Rules\SourceLocation;
use ApiSutra\Serialization\Rules\SourcePathKind;
use Throwable;

class HydrationException extends SdkException
{
    /** Копии диагностики сохраняют первичную ошибку вместо цепочки промежуточных путей. */
    private ?self $diagnosticOrigin = null;
    private ?string $sourceLogPath = null;
    /** @var list<string> */
    private array $sourceLogCandidates = [];

    /** @param list<string> $sourceCandidates */
    public function __construct(
        string|Message $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $reason = null,
        public readonly ?string $path = null,
        public readonly ?string $expected = null,
        public readonly ?string $actual = null,
        public readonly ?string $sourcePath = null,
        public readonly ?SourcePathKind $sourcePathKind = null,
        public readonly array $sourceCandidates = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function invalidValue(string $reason, string $expected, string $actual, string $path = '', ?Throwable $previous = null): self
    {
        return new self(
            new Message('hydration.invalid_value', ['value0' => $path === '' ? '$' : $path, 'expected' => $expected, 'actual' => $actual]),
            previous: $previous,
            reason: $reason,
            path: $path,
            expected: $expected,
            actual: $actual,
        );
    }

    /** Дополняет только структурированную диагностику; значения ответа не включаются. */
    public function prependPath(string $prefix): self
    {
        if ($this->reason === null) {
            return $this;
        }

        $path = $this->path ?? '';
        $path = $prefix . ($path === '' || str_starts_with($path, '[') ? '' : '.') . $path;
        $exception = new self(
            new Message('hydration.invalid_value_path', ['path' => $path, 'value1' => $this->expected ?? 'unknown', 'value2' => $this->actual ?? 'unknown']),
            $this->getCode(),
            $this->diagnosticOrigin ?? $this,
            reason: $this->reason,
            path: $path,
            expected: $this->expected,
            actual: $this->actual,
            sourcePath: $this->sourcePath,
            sourcePathKind: $this->sourcePathKind,
            sourceCandidates: $this->sourceCandidates,
        );
        $exception->diagnosticOrigin = $this->diagnosticOrigin ?? $this;
        $exception->sourceLogPath = $this->sourceLogPath;
        $exception->sourceLogCandidates = $this->sourceLogCandidates;
        return $this->messageLocalization === null ? $exception : $exception->localized($this->messageLocalization);
    }

    /** @internal Добавляет происхождение без изменения DTO-пути и исходной причины. */
    public function withSource(SourceLocation $source): self
    {
        $exception = new self(
            $this->messageDefinition() ?? $this->getMessage(),
            $this->getCode(),
            $this->diagnosticOrigin ?? $this,
            $this->reason,
            $this->path,
            $this->expected,
            $this->actual,
            $source->kind === SourcePathKind::Unavailable ? null : SourceLocation::pointer($source->segments),
            $source->kind,
            $source->candidates,
        );
        $exception->diagnosticOrigin = $this->diagnosticOrigin ?? $this;
        $exception->sourceLogPath = SourceLocation::pointer($source->safeSegments);
        $exception->sourceLogCandidates = $source->safeCandidates;
        return $this->messageLocalization === null ? $exception : $exception->localized($this->messageLocalization);
    }

    /** @internal Внешний unwrap/pagination дополняет только source, отдельно от DTO-пути. */
    public function prependSourcePath(string $prefix): self
    {
        if ($this->sourcePathKind === null || $this->sourcePathKind === SourcePathKind::Unavailable) {
            return $this;
        }
        $pointer = SourceLocation::pointer(explode('.', $prefix));
        $exception = new self(
            $this->messageDefinition() ?? $this->getMessage(),
            $this->getCode(),
            $this->diagnosticOrigin ?? $this,
            $this->reason,
            $this->path,
            $this->expected,
            $this->actual,
            $pointer . $this->sourcePath,
            $this->sourcePathKind,
            array_map(static fn (string $path): string => $pointer . $path, $this->sourceCandidates),
        );
        $exception->diagnosticOrigin = $this->diagnosticOrigin ?? $this;
        $exception->sourceLogPath = $pointer . $this->safeSourcePath();
        $exception->sourceLogCandidates = array_map(
            static fn (string $path): string => $pointer . $path,
            $this->safeSourceCandidates(),
        );
        return $this->messageLocalization === null ? $exception : $exception->localized($this->messageLocalization);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        $context = array_filter([
            'reason' => $this->reason,
            'path' => $this->path,
            'expected' => $this->expected,
            'actual' => $this->actual,
        ], static fn (?string $value): bool => $value !== null);
        if ($this->sourcePathKind !== null) {
            $context += [
                'sourcePath' => $this->sourcePath,
                'sourcePathKind' => $this->sourcePathKind->value,
                'sourceCandidates' => $this->sourceCandidates,
            ];
        }
        return $context;
    }

    /** @return array<string, mixed> */
    public function logContext(): array
    {
        $context = $this->context();
        if ($this->sourcePathKind !== null) {
            $context['sourcePath'] = $this->sourcePathKind === SourcePathKind::Unavailable ? null : $this->safeSourcePath();
            $context['sourceCandidates'] = $this->safeSourceCandidates();
        }
        return $context;
    }

    private function safeSourcePath(): string
    {
        return $this->sourceLogPath ?? self::maskPointer($this->sourcePath ?? '');
    }

    /** @return list<string> */
    private function safeSourceCandidates(): array
    {
        return $this->sourceLogPath !== null
            ? $this->sourceLogCandidates
            : array_map(self::maskPointer(...), $this->sourceCandidates);
    }

    private static function maskPointer(string $pointer): string
    {
        return str_repeat('/*', substr_count($pointer, '/'));
    }
    protected function copyForLocalization(): static
    {
        $copy = new ($this::class)(
            $this->messageDefinition() ?? $this->getMessage(),
            $this->getCode(),
            $this,
            $this->reason,
            $this->path,
            $this->expected,
            $this->actual,
            $this->sourcePath,
            $this->sourcePathKind,
            $this->sourceCandidates,
        );
        $copy->diagnosticOrigin = $this->diagnosticOrigin;
        $copy->sourceLogPath = $this->sourceLogPath;
        $copy->sourceLogCandidates = $this->sourceLogCandidates;
        return $copy;
    }
}
