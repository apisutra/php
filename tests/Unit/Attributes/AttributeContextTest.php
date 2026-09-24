<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\Tests\Stubs\Attributes\ContextProbeAttribute;
use ApiSutra\Tests\Stubs\Attributes\ContextProbeHandler;
use ApiSutra\Tests\Stubs\Requests\ContextProbeRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('AttributeContext', function () {
    it('передаёт classAttributes и PipelineContext в handler', function () {
        ContextProbeHandler::reset();
        $registry = new AttributeRegistry();
        $registry->register(ContextProbeAttribute::class, ContextProbeHandler::class);

        $request = new ContextProbeRequest();
        $context = new PipelineContext(
            request: $request,
            config: new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            traceId: 'trace',
        );

        $registry->processStage($request, $context, PipelineStage::BeforeSend, []);

        $propertyContext = null;
        foreach (ContextProbeHandler::$contexts as $item) {
            if ($item->attribute->value === 'property') {
                $propertyContext = $item;
                break;
            }
        }

        expect($propertyContext)->not->toBeNull()
            ->and($propertyContext?->classAttributes)->toHaveCount(3)
            ->and($propertyContext?->context)->toBe($context)
            ->and($propertyContext?->stage)->toBe(PipelineStage::BeforeSend);
    });
});
