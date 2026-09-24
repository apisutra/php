<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Hooks;

use ApiSutra\Attributes\Hooks\AfterHydrate as AfterHydrateAttribute;
use ApiSutra\Attributes\Hooks\AfterResponse as AfterResponseAttribute;
use ApiSutra\Attributes\Hooks\BeforeHydrate as BeforeHydrateAttribute;
use ApiSutra\Attributes\Hooks\BeforeSend as BeforeSendAttribute;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\VO\Pipeline\PipelineContext;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Exceptions\ControlFlow\ControlFlowException;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

final readonly class HookRunner
{
    public function __construct(
        private HookRegistry $hooks,
    ) {
    }

    public function runHookStage(Hook $hook, RequestInterface $request, PipelineContext $context): void
    {
        try {
            $this->applyHookStage($hook, $request, $context);
        } catch (ControlFlowException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $context->failureCode = ErrorCode::HookError;
            throw $exception;
        }
    }

    public function runBeforeHydrate(RequestInterface $request, PipelineContext $context, array $data): array
    {
        try {
            return $this->applyBeforeHydrate($request, $context, $data);
        } catch (ControlFlowException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $context->failureCode = ErrorCode::HookError;
            throw $exception;
        }
    }

    private function applyHookStage(Hook $hook, RequestInterface $request, PipelineContext $context): void
    {
        if ($hook === Hook::BeforeHydrate) {
            return;
        }

        $dtoClass = $this->resolveDtoClass($hook, $request, $context);
        $handlers = $this->resolveHandlers($hook, $request, $dtoClass);
        $this->runHandlers($handlers, $context);
        $this->runRequestHook($hook, $request, $context);
    }

    /**
     * @return array<int, HookInterface>
     */
    private function resolveAttributeHooks(Hook $hook, RequestInterface $request): array
    {
        $reflection = new ReflectionClass($request);
        $attributeClass = match ($hook) {
            Hook::BeforeSend => BeforeSendAttribute::class,
            Hook::AfterResponse => AfterResponseAttribute::class,
            Hook::BeforeHydrate => BeforeHydrateAttribute::class,
            Hook::AfterHydrate => AfterHydrateAttribute::class,
        };

        $instances = [
            'first' => [],
            'normal' => [],
            'last' => [],
        ];
        foreach ($reflection->getAttributes($attributeClass) as $attribute) {
            $instance = $attribute->newInstance();
            $priority = $instance->priority->value ?? 'normal';
            $instances[$priority][] = $this->hooks->resolveHandler($instance->handler);
        }

        return array_merge(
            $instances['first'],
            $instances['normal'],
            $instances['last'],
        );
    }

    private function applyBeforeHydrate(RequestInterface $request, PipelineContext $context, array $data): array
    {
        $dtoClass = $this->resolveDtoClass(Hook::BeforeHydrate, $request, $context);
        $handlers = $this->resolveHandlers(Hook::BeforeHydrate, $request, $dtoClass);
        if ($handlers !== []) {
            $context->hydrationSourceTransformed = true;
        }
        $data = $this->applyBeforeHydrateHandlers($handlers, $context, $data);
        return $this->applyBeforeHydrateRequestHook($request, $context, $data);
    }

    private function resolveDtoClass(Hook $hook, RequestInterface $request, PipelineContext $context): ?string
    {
        if ($hook === Hook::BeforeHydrate) {
            return $context->dto !== null ? $context->dto::class : $request->getResponseType();
        }

        return $context->dto !== null ? $context->dto::class : null;
    }

    /**
     * @return array<int, HookInterface>
     */
    private function resolveHandlers(Hook $hook, RequestInterface $request, ?string $dtoClass): array
    {
        $handlers = $this->hooks->resolve($hook, $request::class, $dtoClass);
        $attributeHandlers = $this->resolveAttributeHooks($hook, $request);

        return array_merge($handlers, $attributeHandlers);
    }

    /**
     * @param array<int, HookInterface> $handlers
     */
    private function runHandlers(array $handlers, PipelineContext $context): void
    {
        foreach ($handlers as $handler) {
            $handler->handle($context);
        }
    }

    private function runRequestHook(Hook $hook, RequestInterface $request, PipelineContext $context): void
    {
        if (!$request instanceof AbstractRequest) {
            return;
        }

        match ($hook) {
            Hook::BeforeSend => $request->beforeSendInternal($context),
            Hook::AfterResponse => $request->afterResponseInternal($context),
            Hook::AfterHydrate => $request->afterHydrateInternal($context),
        };
    }

    /**
     * @param array<int, HookInterface> $handlers
     */
    private function applyBeforeHydrateHandlers(array $handlers, PipelineContext $context, array $data): array
    {
        foreach ($handlers as $handler) {
            $modified = $handler->handle($context);
            if (is_array($modified)) {
                $data = $modified;
            }
        }

        return $data;
    }

    private function applyBeforeHydrateRequestHook(
        RequestInterface $request,
        PipelineContext $context,
        array $data,
    ): array {
        if (!$request instanceof AbstractRequest) {
            return $data;
        }

        $hook = new ReflectionMethod($request, 'beforeHydrate');
        $bridge = new ReflectionMethod($request, 'beforeHydrateInternal');
        if (
            $hook->getDeclaringClass()->getName() !== AbstractRequest::class
            || $bridge->getDeclaringClass()->getName() !== AbstractRequest::class
        ) {
            $context->hydrationSourceTransformed = true;
        }

        return $request->beforeHydrateInternal($context, $data);
    }
}
