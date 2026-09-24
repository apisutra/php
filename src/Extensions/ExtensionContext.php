<?php

declare(strict_types=1);

namespace ApiSutra\Extensions;

use ApiSutra\Contracts\Interfaces\Attributes\AttributeHandlerInterface;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Contracts\Interfaces\Casting\SerializationCastInterface;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Contracts\Interfaces\Response\ResponseHandlerInterface;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Enums\Hooks\HookPriority;

final class ExtensionContext
{
    public function __construct(
        private readonly ExtensionRegistry $registry,
        private readonly string $extensionName,
    ) {
    }

    public function registerCast(string $type, HydrationCastInterface|SerializationCastInterface $cast): void
    {
        $this->registry->registerCast($type, $cast);
    }

    public function registerHook(
        Hook $type,
        HookInterface $hook,
        HookPriority $priority = HookPriority::Normal,
    ): void {
        $this->registry->registerHook($type, $hook, $priority);
    }

    public function registerResponseHandler(
        string $mime,
        ResponseHandlerInterface $handler,
        bool $override = false,
    ): void {
        $this->registry->registerResponseHandler($mime, $handler, $override, $this->extensionName);
    }

    public function registerAttributeHandler(string $attributeClass, AttributeHandlerInterface $handler): void
    {
        $this->registry->registerAttributeHandler($attributeClass, $handler);
    }
}
