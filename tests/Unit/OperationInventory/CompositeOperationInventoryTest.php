<?php

declare(strict_types=1);

use ApiSutra\OperationInventory\CompositeOperationInventory;
use ApiSutra\OperationInventory\OperationInventory;
use ApiSutra\OperationInventory\OperationDescriptorView;

function apisutraMakeView(string $requestClass, ?string $endpoint = null): OperationDescriptorView
{
    return new OperationDescriptorView(
        requestClass: $requestClass,
        httpMethod: null,
        endpoint: $endpoint,
        responseType: null,
        operationDescriptor: null,
        hasDownload: false,
        hasNoAuth: false,
        skipCredentialsEnrichment: false,
    );
}

describe('CompositeOperationInventory', function () {
    it('all() конкатенирует элементы в порядке передачи без пересортировки', function () {
        $first = new OperationInventory([
            apisutraMakeView('Z\\First'),
            apisutraMakeView('A\\Second'),
        ]);
        $second = new OperationInventory([
            apisutraMakeView('M\\Third'),
        ]);

        $composite = new CompositeOperationInventory([$first, $second]);
        $classes = array_map(static fn (OperationDescriptorView $v) => $v->requestClass, $composite->all());

        expect($classes)->toBe(['Z\\First', 'A\\Second', 'M\\Third']);
    });

    it('forRequest() возвращает первое совпадение по порядку', function () {
        $first = new OperationInventory([apisutraMakeView('Same\\Request', '/from-first')]);
        $second = new OperationInventory([apisutraMakeView('Same\\Request', '/from-second')]);

        $composite = new CompositeOperationInventory([$first, $second]);

        expect($composite->forRequest('Same\\Request')?->endpoint)->toBe('/from-first');
    });

    it('forRequest() возвращает null когда ни в одном inventory нет request', function () {
        $first = new OperationInventory([apisutraMakeView('A')]);
        $second = new OperationInventory([apisutraMakeView('B')]);

        $composite = new CompositeOperationInventory([$first, $second]);

        expect($composite->forRequest('NotExist'))->toBeNull();
    });

    it('forEndpoint() конкатенирует совпадения из всех inventories', function () {
        $first = new OperationInventory([
            apisutraMakeView('A', '/shared'),
            apisutraMakeView('B', '/other'),
        ]);
        $second = new OperationInventory([
            apisutraMakeView('C', '/shared'),
        ]);

        $composite = new CompositeOperationInventory([$first, $second]);
        $classes = array_map(
            static fn (OperationDescriptorView $v) => $v->requestClass,
            $composite->forEndpoint('/shared'),
        );

        expect($classes)->toBe(['A', 'C']);
    });

    it('пустой набор inventories даёт пустой all()', function () {
        $composite = new CompositeOperationInventory([]);

        expect($composite->all())->toBe([])
            ->and($composite->forRequest('X'))->toBeNull()
            ->and($composite->forEndpoint('/x'))->toBe([]);
    });

    it('inventories() отдаёт исходные per-service inventory', function () {
        $first = new OperationInventory([apisutraMakeView('A')]);
        $second = new OperationInventory([apisutraMakeView('B')]);

        $composite = new CompositeOperationInventory([$first, $second]);

        expect($composite->inventories())->toBe([$first, $second]);
    });
});
