<?php

declare(strict_types=1);

namespace ApiSutra\Extensions;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\AttributeRegistry;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Contracts\Interfaces\Attributes\AttributeHandlerInterface;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Contracts\Interfaces\Casting\SerializationCastInterface;
use ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Contracts\Interfaces\Response\ResponseHandlerInterface;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Enums\Hooks\HookPriority;
use ApiSutra\Exceptions\Extension\ExtensionConflictException;
use ApiSutra\Exceptions\Extension\ExtensionDisabledException;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;

final class ExtensionRegistry
{
    /**
     * @var array<string, array{extension: ExtensionInterface, booted: bool}>
     */
    private array $extensions = [];

    /**
     * @var array<string, array{handler: ResponseHandlerInterface, extension: string}>
     */
    private array $responseHandlers = [];

    public function __construct(
        private readonly CastRegistry $casts,
        private readonly HookRegistry $hooks,
        private readonly AttributeRegistry $attributes,
    ) {
    }

    public function register(ExtensionInterface $extension): void
    {
        $extension->checkDependencies();
        $context = new ExtensionContext($this, $extension->getName());
        $extension->register($context);

        $this->extensions[$extension->getName()] = [
            'extension' => $extension,
            'booted' => false,
        ];
    }

    public function get(string $name): ?ExtensionInterface
    {
        return $this->extensions[$name]['extension'] ?? null;
    }

    /**
     * @return array<ExtensionInterface>
     */
    public function all(): array
    {
        return array_map(
            fn (array $item) => $item['extension'],
            $this->extensions,
        );
    }

    public function registerCast(string $type, HydrationCastInterface|SerializationCastInterface $cast): void
    {
        $this->casts->register($type, $cast);
    }

    public function registerHook(Hook $type, HookInterface $hook, HookPriority $priority): void
    {
        $this->hooks->on($type, $hook, priority: $priority);
    }

    public function registerResponseHandler(string $mime, ResponseHandlerInterface $handler, bool $override, string $extension): void
    {
        if (!$override && isset($this->responseHandlers[$mime])) {
            throw new ExtensionConflictException(new Message('extensions.handler_for_already_registered', ['mime' => $mime]));
        }

        $this->responseHandlers[$mime] = [
            'handler' => $handler,
            'extension' => $extension,
        ];
    }

    public function registerAttributeHandler(string $attributeClass, AttributeHandlerInterface $handler): void
    {
        $this->attributes->register($attributeClass, $handler::class);
    }

    public function resolveResponseHandler(?ProviderResponse $response, PipelineContext $context): ?ResponseHandlerInterface
    {
        if ($response === null) {
            return null;
        }

        $contentType = $response->header('Content-Type') ?? '';
        $candidates = [];

        foreach ($this->responseHandlers as $mime => $data) {
            $priority = $this->matchMimePriority($contentType, $mime);
            if ($priority === 0 && !$data['handler']->supports($response)) {
                continue;
            }

            if ($priority === 0) {
                $priority = 1;
            }

            $candidates[] = [
                'priority' => $priority,
                'handler' => $data['handler'],
                'extension' => $data['extension'],
            ];
        }

        usort($candidates, static fn (array $a, array $b) => $b['priority'] <=> $a['priority']);

        foreach ($candidates as $candidate) {
            $extensionName = $candidate['extension'];
            $extension = $this->extensions[$extensionName]['extension'] ?? null;
            if ($extension === null) {
                continue;
            }

            if (!$extension->isEnabled()) {
                throw new ExtensionDisabledException(new Message('extensions.extension_is_disabled', ['extensionName' => $extensionName]));
            }

            if ($this->extensions[$extensionName]['booted'] === false) {
                $extension->boot($context->config);
                $this->extensions[$extensionName]['booted'] = true;
            }

            return $candidate['handler'];
        }

        return null;
    }

    private function matchMimePriority(string $contentType, string $mime): int
    {
        if ($mime === '*') {
            return 1;
        }

        if (str_ends_with($mime, '/*')) {
            $prefix = substr($mime, 0, -2);
            return str_starts_with($contentType, $prefix . '/') ? 2 : 0;
        }

        if (str_starts_with($contentType, $mime)) {
            $next = substr($contentType, strlen($mime), 1);
            if ($next === '' || $next === ';') {
                return 3;
            }
        }

        return 0;
    }
}
