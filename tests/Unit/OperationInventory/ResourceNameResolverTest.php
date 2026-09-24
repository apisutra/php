<?php

declare(strict_types=1);

use ApiSutra\OperationInventory\ResourceNameResolver;

describe('ResourceNameResolver', function () {
    it('извлекает иерархию ресурсов между Resources и Requests', function () {
        $resolver = new ResourceNameResolver();

        $path = $resolver->resolveResourcePath(
            'Foo\\Bar\\Resources\\Reports\\Tasks\\Requests\\CreateByFio\\CreateByFioRequest',
        );

        expect($path)->toBe(['Reports', 'Tasks']);
    });

    it('возвращает single-element путь для плоского ресурса', function () {
        $resolver = new ResourceNameResolver();

        $path = $resolver->resolveResourcePath(
            'Foo\\Bar\\Resources\\Files\\Requests\\DownloadAttachmentRequest',
        );

        expect($path)->toBe(['Files']);
    });

    it('возвращает null если в namespace нет Resources/Requests', function () {
        $resolver = new ResourceNameResolver();

        $path = $resolver->resolveResourcePath('Foo\\Bar\\StandaloneRequest');

        expect($path)->toBeNull();
    });

    it('возвращает null если между Resources и Requests нет сегментов', function () {
        $resolver = new ResourceNameResolver();

        $path = $resolver->resolveResourcePath(
            'Foo\\Resources\\Requests\\BrokenRequest',
        );

        expect($path)->toBeNull();
    });

    it('возвращает null если есть только Resources без Requests', function () {
        $resolver = new ResourceNameResolver();

        $path = $resolver->resolveResourcePath('Foo\\Resources\\Reports\\Some');

        expect($path)->toBeNull();
    });
});
