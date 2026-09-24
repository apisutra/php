<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\Tests\Stubs\Attributes\TagAttribute;
use ApiSutra\Tests\Stubs\Attributes\TagAttributeHandler;
use ApiSutra\Tests\Stubs\Requests\AttributeStageRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('AttributeRegistry', function () {
    it('обрабатывает атрибуты класса и свойства', function () {
        $registry = new AttributeRegistry();
        $registry->register(TagAttribute::class, TagAttributeHandler::class);

        $request = new AttributeStageRequest();
        $context = new PipelineContext(
            request: $request,
            config: new ClientConfig(
                baseUrl: 'https://provider.test',
                environment: Environment::Testing,
            ),
            traceId: 'trace',
        );

        $result = $registry->processStage($request, $context, PipelineStage::BeforeSend, []);

        expect($result)->toBe(['class', 'property']);
    });
});
