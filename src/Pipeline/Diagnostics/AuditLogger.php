<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Diagnostics;

use ApiSutra\Localization\Message;
use ApiSutra\Config\ClientConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;
use Closure;

final readonly class AuditLogger
{
    /**
     * @var array<string, int>
     */
    private const array LOG_LEVELS = [
        LogLevel::DEBUG => 100,
        LogLevel::INFO => 200,
        LogLevel::NOTICE => 250,
        LogLevel::WARNING => 300,
        LogLevel::ERROR => 400,
        LogLevel::CRITICAL => 500,
        LogLevel::ALERT => 550,
        LogLevel::EMERGENCY => 600,
    ];

    public function __construct(
        private ?ClientConfig $config = null,
    ) {
    }

    /**
     * @param array<string, mixed>|Closure(): array<string, mixed> $context
     * @param list<string> $secretFields
     */
    public function log(string $level, string|Message $message, array|Closure $context = [], array $secretFields = []): void
    {
        $logger = $this->config?->logger;
        if (!$logger instanceof LoggerInterface) {
            return;
        }

        if (!$this->shouldLog($level)) {
            return;
        }

        try {
            $context = $context instanceof Closure ? $context() : $context;
            if ($message instanceof Message) {
                $context['event'] ??= $message->key;
            }
            $context['trace'] ??= $context['traceId'] ?? null;
            $logger->log($level, $message instanceof Message ? $message->render($this->config->localization) : $message, $this->config->redaction->withFields($secretFields)->context($context));
        } catch (Throwable) {
            // Диагностика не меняет результат и не пытается рекурсивно логировать отказ sink.
        }
    }

    private function shouldLog(string $level): bool
    {
        $min = self::LOG_LEVELS[$this->config->logLevel] ?? self::LOG_LEVELS[LogLevel::INFO];
        $current = self::LOG_LEVELS[$level] ?? self::LOG_LEVELS[LogLevel::INFO];

        return $current >= $min;
    }
}
