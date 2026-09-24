<?php

declare(strict_types=1);

namespace ApiSutra\Hooks;

use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Enums\Hooks\HookPriority;
use Closure;

final class HookRegistry
{
    /**
     * @var array<string, array<string, array<string, array<string, HookInterface|string>>>>
     */
    private array $hooks = [];

    private readonly ?Closure $resolver;

    /**
     * @param callable(string):object|null $resolver
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver !== null ? Closure::fromCallable($resolver) : null;
    }

    /**
     * Регистрация обработчика
     *
     * @param HookInterface|string $handler
     * @param array<string>|null $for
     */
    public function on(
        Hook $type,
        HookInterface|string $handler,
        ?array $for = null,
        ?string $forDto = null,
        HookPriority $priority = HookPriority::Normal,
        ?string $name = null,
    ): void {
        $name = $name ?? (is_string($handler) ? $handler : spl_object_hash($handler));
        if ($forDto !== null) {
            $bucketKey = 'dto:' . $forDto;
            $this->hooks[$type->value][$bucketKey][$priority->value][$name] = $handler;
            return;
        }

        if ($for !== null && $for !== []) {
            foreach ($for as $class) {
                $bucketKey = 'request:' . $class;
                $this->hooks[$type->value][$bucketKey][$priority->value][$name] = $handler;
            }
            return;
        }

        $this->hooks[$type->value]['global'][$priority->value][$name] = $handler;
    }

    public function remove(Hook $type, ?string $name = null): void
    {
        if (!isset($this->hooks[$type->value])) {
            return;
        }

        if ($name === null) {
            unset($this->hooks[$type->value]);
            return;
        }

        foreach ($this->hooks[$type->value] as $bucketKey => $priorities) {
            foreach ($priorities as $priority => $handlers) {
                if (isset($handlers[$name])) {
                    unset($this->hooks[$type->value][$bucketKey][$priority][$name]);
                }
            }
        }
    }

    /**
     * @return array<int, HookInterface>
     */
    public function resolve(
        Hook $type,
        ?string $requestClass = null,
        ?string $dtoClass = null,
    ): array {
        $result = [];

        $result = array_merge($result, $this->collect($type, 'global'));

        if ($requestClass !== null) {
            $result = array_merge($result, $this->collect($type, 'request:' . $requestClass));
        }

        if ($dtoClass !== null) {
            $result = array_merge($result, $this->collect($type, 'dto:' . $dtoClass));
        }

        return $result;
    }

    public function resolveHandler(HookInterface|string $handler): HookInterface
    {
        if ($handler instanceof HookInterface) {
            return $handler;
        }

        $resolver = $this->resolver ?? fn (string $class) => new $class();
        $instance = $resolver($handler);

        return $instance;
    }


    /**
     * @return array<int, HookInterface>
     */
    private function collect(Hook $type, string $bucketKey): array
    {
        $handlers = $this->hooks[$type->value][$bucketKey] ?? [];
        $result = [];

        foreach ([HookPriority::First, HookPriority::Normal, HookPriority::Last] as $priority) {
            foreach ($handlers[$priority->value] ?? [] as $handler) {
                $result[] = $this->resolveHandler($handler);
            }
        }

        return $result;
    }
}
