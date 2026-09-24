<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Diagnostics;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Diagnostics\ExecutionObserverInterface;
use ApiSutra\Diagnostics\ExecutionSnapshot;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Diagnostics\RedactionPolicy;
use ApiSutra\Support\ContainerProviderRegistry;
use Closure;
use Throwable;

/** @internal Изоляция получателя и маскирование перед пересечением границы ядра. */
final readonly class ExecutionObservation
{
    private function __construct(private ExecutionObserverInterface $observer, private RedactionPolicy $redaction, public ?string $clientLabel)
    {
    }

    public static function forConfig(ClientConfig $config): ?self
    {
        try {
            $provider = ContainerProviderRegistry::resolve($config->containerProvider);
            if (!$provider->bound(ExecutionObserverInterface::class)) {
                return null;
            }
            $observer = $provider->make(ExecutionObserverInterface::class);
            return $observer instanceof ExecutionObserverInterface ? new self($observer, $config->redaction, $config->diagnosticLabel) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function start(ExecutionTrace $trace): void
    {
        $this->safely(fn () => $this->observer->started($trace));
    }

    public function release(ExecutionTrace $trace): void
    {
        $this->safely(fn () => $this->observer->released($trace));
    }

    public function includeAttempts(): bool
    {
        try {
            return $this->observer->includeAttempts();
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, scalar|null|list<array<string, scalar|null>>> $data */
    public function complete(array $data): void
    {
        $this->safely(function () use ($data): void {
            foreach ($data as &$value) {
                if (is_string($value)) {
                    $value = substr($value, 0, 512);
                }
            }
            unset($value);
            $safe = $this->redaction->context($data);
            $this->observer->completed(new ExecutionSnapshot($safe));
        });
    }

    private function safely(Closure $callback): void
    {
        try {
            $callback();
        } catch (Throwable) {
            // Наблюдение не меняет исход и не пишет отказ в собственный exporter.
        }
    }
}
